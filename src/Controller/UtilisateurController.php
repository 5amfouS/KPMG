<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Entreprise;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;
use App\Entity\Employe;
use App\Entity\Mdp;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;
use TCPDF;
use Psr\Log\LoggerInterface;
use setasign\Fpdi\TcpdfFpdi;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;


function generateRandomPassword($length = 5): string {
    return substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz123456789'), 0, $length);
}
final class UtilisateurController extends AbstractController
{


    #[Route('/utilisateurs', name: 'app_utilisateurs')]
    public function listUtilisateurs(Request $request,EntityManagerInterface $em): Response
    {

        $session = $request->getSession();

        if (!$session->has('user_id')) {
            return $this->redirectToRoute('app_signin');
        }
        else{
            if($session->get('user_role')!=="ADMIN")
                return $this->redirectToRoute('app_mailing');

        }

        $utilisateurs = $em->getRepository(Utilisateur::class)->findBy(['role' => 'USER',]);


        return $this->render('utilisateur/utilisateurs.html.twig', [
            'utilisateurs' => $utilisateurs,
        ]);
    }

    #[Route('/entreprise/{id}/employes', name: 'app_employes_entreprise')]
    public function employesParEntreprise(int $id, EntityManagerInterface $em, Request $request): Response
    {
        $session = $request->getSession();

        if (!$session->has('user_id')) {
            return $this->redirectToRoute('app_signin');
        }

        if ($session->get('user_role') === "ADMIN") {
            return $this->redirectToRoute('app_utilisateurs');
        }

        $entreprise = $em->getRepository(Entreprise::class)->find($id);

        if (!$entreprise) {
            throw $this->createNotFoundException("Entreprise introuvable.");
        }

        $employes = $em->getRepository(Employe::class)->findBy(['entreprise' => $entreprise]);

        return $this->render('utilisateur/employes.html.twig', [
            'employes' => $employes,
            'entreprise' => $entreprise,
        ]);
    }



    #[Route('/entreprises', name: 'app_entreprises')]
    public function listEntreprises(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();

        if (!$session->has('user_id')) {
            return $this->redirectToRoute('app_signin');
        }

        if ($session->get('user_role') === "ADMIN") {
            return $this->redirectToRoute('app_utilisateurs');
        }

        $entreprises = $em->getRepository(Entreprise::class)->findAll();

        // Tableau [id_entreprise => nb_employes]
        $employeCounts = [];
        foreach ($entreprises as $entreprise) {
            $count = $em->createQueryBuilder()
                ->select('COUNT(e.id)')
                ->from(Employe::class, 'e')
                ->where('e.entreprise = :ent')
                ->setParameter('ent', $entreprise->getId())
                ->getQuery()
                ->getSingleScalarResult();

            $employeCounts[$entreprise->getId()] = (int) $count;
        }

        return $this->render('utilisateur/entreprises.html.twig', [
            'entreprises' => $entreprises,
            'employeCounts' => $employeCounts,
        ]);
    }

    #[Route('/entreprise/{entreprise_id}/employe/ajouter', name: 'app_ajouter_employe')]
    public function ajouterEmploye(Request $request, EntityManagerInterface $em, int $entreprise_id): Response
    {
        $entreprise = $em->getRepository(Entreprise::class)->find($entreprise_id);

        if (!$entreprise) {
            throw $this->createNotFoundException("Entreprise introuvable.");
        }

        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $id = trim($request->request->get('id'));
            $nom = trim($request->request->get('nom'));
            $email = trim($request->request->get('email'));

            if (!ctype_digit($id)) {
                $error = "Le matricule (ID) doit être un entier.";
            } elseif (empty($nom)) {
                $error = "Le nom est obligatoire.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Adresse email invalide.";
            } elseif ($em->getRepository(Employe::class)->find((int)$id)) {
                $error = "Un employé avec cet ID existe déjà.";
            } else {
                $employe = new Employe();
                $employe->setId((int)$id); // nécessite que l'entité ait setId()
                $employe->setNom($nom);
                $employe->setEmail($email);
                $employe->setEntreprise($entreprise);

                $em->persist($employe);
                $em->flush();

                $success = "Employé ajouté avec succès.";
            }
        }


        return $this->render('utilisateur/ajouteremploye.html.twig', [
            'entreprise' => $entreprise,
            'error' => $error,
            'success' => $success,
        ]);
    }

    #[Route('/employe/{id}/modifier', name: 'app_modifier_employe')]
    public function modifierEmploye(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $employe = $em->getRepository(Employe::class)->find($id);
        if (!$employe) {
            throw $this->createNotFoundException("Employé introuvable.");
        }

        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom'));
            $email = trim($request->request->get('email'));

            if (empty($nom)) {
                $error = "Le nom est obligatoire.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Adresse email invalide.";
            } else {
                $employe->setNom($nom);
                $employe->setEmail($email);
                $em->flush();
                $success = "Employé mis à jour avec succès.";
            }
        }

        return $this->render('utilisateur/modifieremploye.html.twig', [
            'employe' => $employe,
            'error' => $error,
            'success' => $success,
        ]);
    }


    #[Route('/entreprise/{id}/modifier', name: 'app_modifierentreprise', methods: ['GET', 'POST'])]
    public function modifierEntreprise(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $entreprise = $em->getRepository(Entreprise::class)->find($id);

        if (!$entreprise) {
            throw $this->createNotFoundException("Entreprise introuvable.");
        }

        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom'));
            $contact = trim($request->request->get('contact'));

            if (empty($nom)) {
                $error = "Le nom de l'entreprise est obligatoire.";
            } elseif (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                $error = "Le contact email est invalide.";
            } else {
                $entreprise->setNom($nom);
                $entreprise->setContact($contact);

                $em->flush();

                $success = "Entreprise mise à jour avec succès.";
            }
        }

        return $this->render('utilisateur/modifierentreprise.html.twig', [
            'entreprise' => $entreprise,
            'error' => $error,
            'success' => $success,
        ]);
    }




    #[Route('/monespace', name: 'app_monespace')]
    public function monespace(Request $request,EntityManagerInterface $em, ValidatorInterface $validator): Response
    {

    $session = $request->getSession();

    if (!$session->has('user_id')) {
        return $this->redirectToRoute('app_signin');
    }

    if ($session->has('user_id')) {
        $user = $em->getRepository(Utilisateur::class)->find($session->get('user_id'));
        if ($user && $user->getBloque() === 'oui') {
            $session->invalidate();
            $this->addFlash('error', 'Votre compte a été bloqué.');
            return $this->redirectToRoute('app_signin');
        }
    }

    $user = $em->getRepository(Utilisateur::class)->find($session->get('user_id'));

    if ($request->isMethod('POST')) {
        $ancien = $request->request->get('ancien');
        $nouveau = $request->request->get('nouveau');
        $confirmer = $request->request->get('confirmer');

        if ($nouveau !== $confirmer) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_monespace');
        }

        $hasher = new NativePasswordHasher();

        // Vérifier l'ancien mot de passe
        $dernierMdp = $em->getRepository(Mdp::class)->findOneBy(
            ['utilisateur' => $user],
            ['date_creation' => 'DESC']
        );

        if (!$dernierMdp || !$hasher->verify($dernierMdp->getMdp(), $ancien)) {
            $this->addFlash('error', 'Ancien mot de passe incorrect.');
            return $this->redirectToRoute('app_monespace');
        }

        // Vérifier que le nouveau mot de passe n'est pas parmi les 5 derniers
        $anciensMdp = $em->getRepository(Mdp::class)->createQueryBuilder('m')
            ->where('m.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('m.date_creation', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($anciensMdp as $mdpObj) {
            if ($hasher->verify($mdpObj->getMdp(), $nouveau)) {
                $this->addFlash('error', 'Le nouveau mot de passe ne doit pas être identique à l’un des 5 derniers.');
                return $this->redirectToRoute('app_monespace');
            }
        }


        //permet d'enlever les anciens mdp quand je depsse 5 mdp

        if (count($anciensMdp) >= 5) {
            $plusAncienMdp = $em->getRepository(Mdp::class)->createQueryBuilder('m')
                ->where('m.utilisateur = :user')
                ->setParameter('user', $user)
                ->orderBy('m.date_creation', 'ASC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($plusAncienMdp) {
                $em->remove($plusAncienMdp);
            }
        }

        // Si tout est bon, enregistrer le nouveau mot de passe
        $newMdp = new Mdp();
        $newMdp->setUtilisateur($user);
        $newMdp->setMdp($hasher->hash($nouveau));
        $newMdp->setDateCreation(new \DateTime());

        $em->persist($newMdp);
        $em->flush();

        $this->addFlash('success', 'Mot de passe modifié avec succès.');
        return $this->redirectToRoute('app_mailing');
    }

        return $this->render('utilisateur/monespace.html.twig');
    }


    #[Route('/utilisateur/{id}/toggle-block', name: 'toggle_user_block')]
    public function toggleUserBlock(int $id, EntityManagerInterface $em): Response
    {
        $utilisateur = $em->getRepository(Utilisateur::class)->find($id);

        if (!$utilisateur) {
            throw $this->createNotFoundException("Utilisateur non trouvé.");
        }

        // Inverser le champ bloque (si "non" -> "oui", si "oui" -> "non")
        $utilisateur->setBloque($utilisateur->getBloque() === 'non' ? 'oui' : 'non');

        $em->persist($utilisateur);
        $em->flush();

        return $this->redirectToRoute('app_utilisateurs');
    }


#[Route('/mailing', name: 'app_mailing', methods: ['GET', 'POST'])]
public function mailing(Request $request, EntityManagerInterface $em, LoggerInterface $logger): Response
{
    $session = $request->getSession();

    if ($session->has('user_id')) {
        $user = $em->getRepository(Utilisateur::class)->find($session->get('user_id'));
        if ($user && $user->getBloque() === 'oui') {
            $session->invalidate();
            $this->addFlash('error', 'Votre compte a été bloqué.');
            return $this->redirectToRoute('app_signin');
        }
    }

    if (!$session->has('user_id')) {
        return $this->redirectToRoute('app_signin');
    } elseif ($session->get('user_role') == "ADMIN") {
        return $this->redirectToRoute('app_utilisateurs');
    }

    $entreprises = $em->getRepository(Entreprise::class)->findAll();

    if ($request->isMethod('POST')) {
        $entrepriseId = $request->get('entreprise_id');
        $zipFile = $request->files->get('zipfile');

        if (!$zipFile) {
            $this->addFlash('error', 'Aucun fichier ZIP reçu.');
            return $this->render('utilisateur/mailing.html.twig', ['entreprises' => $entreprises]);
        }

        $entreprise = $em->getRepository(Entreprise::class)->find($entrepriseId);
        if (!$entreprise) {
            $this->addFlash('error', 'Entreprise introuvable.');
            return $this->render('utilisateur/mailing.html.twig', ['entreprises' => $entreprises]);
        }

        $nomEntreprise = $entreprise->getNom();
        $tempDir = sys_get_temp_dir() . '/' . uniqid('paie_', true);
        mkdir($tempDir, 0777, true);

        $zip = new ZipArchive();
        if ($zip->open($zipFile->getPathname()) === true) {
            $zip->extractTo($tempDir);
            $zip->close();
        } else {
            $this->addFlash('error', 'Impossible d’extraire le fichier ZIP.');
            return $this->render('utilisateur/mailing.html.twig', ['entreprises' => $entreprises]);
        }

        $employes = $em->getRepository(Employe::class)->findBy(['entreprise' => $entreprise]);
        $errors = 0;

        foreach ($employes as $employe) {
            $id = $employe->getId();
            $email = $employe->getEmail();
            $nom = $employe->getNom();
            $pathToPdf = "$tempDir/paie/$nomEntreprise/$id/fiche_de_paie_modele.pdf";
            $securedPdf = "$tempDir/secured_$id.pdf";
            $pwd = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 5);

            try {
                if (!file_exists($pathToPdf)) {
                    $logger->error("PDF introuvable pour employé ID $id : $pathToPdf");
                    $errors++;
                    continue;
                }

                // ✅ Protéger le vrai PDF avec mot de passe
                $pdf = new TcpdfFpdi();

                $pdf->SetPrintHeader(false);
                $pdf->SetPrintFooter(false);
                $pdf->SetProtection(['print'], $pwd);

                $pdf->AddPage();
                $pageCount = $pdf->setSourceFile($pathToPdf);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $tplId = $pdf->importPage($i);
                    $pdf->useTemplate($tplId);
                    if ($i < $pageCount) {
                        $pdf->AddPage();
                    }
                }

                $pdf->Output($securedPdf, 'F');

                if (!file_exists($securedPdf)) {
                    throw new \Exception("Le PDF protégé n’a pas été généré !");
                }

                // ✅ Envoi WhatsApp
                $message = "Bonjour $nom, votre fiche de paie est disponible.\n🔐 Mot de passe : $pwd";
                $url = "https://api.callmebot.com/whatsapp.php?phone=+21694653884&text=" . urlencode($message) . "&apikey=3830934";
                file_get_contents($url);

                // ✅ Envoi Email
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'ni4loupat@gmail.com';
                $mail->Password = 'yhtkxjchaennxpdx';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('ni4loupat@gmail.com', $nomEntreprise);
                $mail->addAddress($email, $nom);
                $mail->isHTML(true);
                $mail->Subject = 'Votre fiche de paie';
                $mail->Body = 'Bonjour, veuillez trouver en pièce jointe votre fiche de paie.';

                $mail->addAttachment($securedPdf);
                $mail->send();
            } catch (\Throwable $e) {
                $logger->error("Erreur génération ou envoi PDF pour employé $id : " . $e->getMessage());
                $errors++;
                continue;
            }
        }

        $this->addFlash('success', 'Envoi terminé. ' . (count($employes) - $errors) . ' emails envoyés.');
        return $this->redirectToRoute('app_mailing');
    }

    return $this->render('utilisateur/mailing.html.twig', [
        'entreprises' => $entreprises
    ]);
}


    #[Route('/AjouterEntreprise', name: 'app_ajouterentreprise', methods: ['GET', 'POST'])]
    public function AjouterEntreprise(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();

        if (!$session->has('user_id')) {
            return $this->redirectToRoute('app_signin');
        }

        if ($session->get('user_role') === "ADMIN") {
            return $this->redirectToRoute('app_utilsateurs');
        }

        $nom = '';
        $email = '';
        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $email = trim($request->request->get('email', ''));

            if (empty($nom)) {
                $error = "Le nom de l'entreprise est obligatoire.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "L'adresse email est invalide.";
            } else {
                // Vérifie si une entreprise avec ce nom existe déjà
                $existing = $em->getRepository(Entreprise::class)->findOneBy(['nom' => $nom]);

                if ($existing) {
                    $error = "Une entreprise avec ce nom existe déjà.";
                } else {
                    $entreprise = new Entreprise();
                    $entreprise->setNom($nom);
                    $entreprise->setContact($email);

                    $em->persist($entreprise);
                    $em->flush();

                    $success = "Entreprise ajoutée avec succès !";
                    $nom = '';
                    $email = '';
                }
            }
        }

        return $this->render('utilisateur/ajouterentreprise.html.twig', [
            'nom' => $nom,
            'email' => $email,
            'error' => $error,
            'success' => $success,
        ]);
    }



    #[Route('/entreprise/{id}/supprimer', name: 'app_supprimer_entreprise', methods: ['POST'])]
    public function supprimerEntreprise(int $id, EntityManagerInterface $em, Request $request): Response
    {
        $entreprise = $em->getRepository(Entreprise::class)->find($id);

        if (!$entreprise) {
            throw $this->createNotFoundException("Entreprise introuvable");
        }

        $submittedToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $entreprise->getId(), $submittedToken)) {
            // Supprime les employés liés
            $employes = $em->getRepository(Employe::class)->findBy(['entreprise' => $entreprise]);
            foreach ($employes as $employe) {
                $em->remove($employe);
            }

            $em->remove($entreprise);
            $em->flush();

            $this->addFlash('success', 'Entreprise supprimée avec succès.');
        }

        return $this->redirectToRoute('app_entreprises');
    }

    #[Route('/employe/{id}/supprimer', name: 'app_supprimer_employe', methods: ['POST'])]
    public function supprimerEmploye(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $employe = $em->getRepository(Employe::class)->find($id);
        if (!$employe) {
            throw $this->createNotFoundException("Employé introuvable.");
        }

        $submittedToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $employe->getId(), $submittedToken)) {
            $entrepriseId = $employe->getEntreprise()->getId();
            $em->remove($employe);
            $em->flush();
        }

        return $this->redirectToRoute('app_employes_entreprise', [
            'id' => $entrepriseId
        ]);
    }



    #[Route('/entreprise/{entreprise_id}/import', name: 'app_importer_employes', methods: ['POST'])]
    public function importerEmployes(int $entreprise_id, Request $request, EntityManagerInterface $em): Response
    {
        $entreprise = $em->getRepository(Entreprise::class)->find($entreprise_id);
        if (!$entreprise) {
            throw $this->createNotFoundException("Entreprise introuvable.");
        }

        $file = $request->files->get('excel_file');
        if (!$file) {
            $this->addFlash('error', "Aucun fichier n’a été sélectionné.");
            return $this->redirectToRoute('app_employes_entreprise', ['id' => $entreprise_id]);
        }

        $spreadsheet = IOFactory::load($file->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $nbAjoutes = 0;
        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // Ignorer l'entête

            $idXls = trim($row[0] ?? '');
            $nomEntrepriseXls = strtolower(trim($row[1] ?? ''));
            $nomXls = trim($row[2] ?? '');
            $emailXls = trim($row[3] ?? '');

            if (
                $nomEntrepriseXls === strtolower(trim($entreprise->getNom())) &&
                !empty($nomXls) &&
                filter_var($emailXls, FILTER_VALIDATE_EMAIL)
            ) {
                // Vérifie si l'ID est déjà utilisé (évite les doublons)
                $existant = $em->getRepository(Employe::class)->find($idXls);
                if ($existant) {
                    continue; // On ignore si l'ID existe déjà
                }

                $employe = new Employe();
                $employe->setId((int) $idXls); // Nécessite que l'ID ne soit pas auto-généré
                $employe->setNom($nomXls);
                $employe->setEmail($emailXls);
                $employe->setEntreprise($entreprise);
                $em->persist($employe);
                $nbAjoutes++;
            }
        }


        if($nbAjoutes>0){
            $entreprise->setFichier('oui');
        }
        $em->persist($entreprise);
        $em->flush();
        $this->addFlash('success', "$nbAjoutes employé(s) importé(s) avec succès.");
        return $this->redirectToRoute('app_employes_entreprise', ['id' => $entreprise_id]);
    }


}
