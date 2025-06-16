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


    #[Route('/monespace', name: 'app_monespace')]
    public function monespace(Request $request,EntityManagerInterface $em): Response
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
}
