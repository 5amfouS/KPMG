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


#[Route('/mailing', name: 'app_mailing', methods:['GET','POST'])]
public function mailing(Request $request, EntityManagerInterface $em): Response
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


    $session = $request->getSession();

    if (!$session->has('user_id')) {
        return $this->redirectToRoute('app_signin');
    }
    else{
        if($session->get('user_role')=="ADMIN")
            return $this->redirectToRoute('app_utilisateurs');
    }
    $entreprises = $em->getRepository(entreprise::class)->findAll();

    if ($request->isMethod('POST')) {
        $entrepriseId = $request->get('entreprise_id');
        $zipFile = $request->files->get('zipfile');

        if (!$zipFile) {
            $this->addFlash('error', 'Aucun fichier ZIP reçu.');
            return $this->render('utilisateur/mailing.html.twig', ['entreprises' => $entreprises]);
        }

        $entreprise = $em->getRepository(entreprise::class)->find($entrepriseId);
        if (!$entreprise) {
            $this->addFlash('error', 'Entreprise introuvable.');
            return $this->render('utilisateur/mailing.html.twig', ['entreprises' => $entreprises]);
        }

        $nomEntreprise = $entreprise->getNom();

        $tempDir = sys_get_temp_dir() . '/' . uniqid('paie_', true);
        mkdir($tempDir, 0777, true);

        $zipPath = $zipFile->getPathname();
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($tempDir);
            $zip->close();
        } else {
            $this->addFlash('error', 'Impossible d’extraire le fichier ZIP.');
            return $this->render('utilisateur/mailing.html.twig', ['entreprises' => $entreprises]);
        }

        // Récupérer les employés de l'entreprise
        $employes = $em->getRepository(Employe::class)->findBy(['entreprise' => $entreprise]);

        $errors = 0;
        foreach ($employes as $employe) {
            $id = $employe->getId();
            $email = $employe->getEmail();
            $pathToPdf = $tempDir . "/paie/$nomEntreprise/$id/fiche_de_paie_modele.pdf";

            if (!file_exists($pathToPdf)) {
                $errors++;
                continue;
            }

            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'ni4loupat@gmail.com';
                $mail->Password = 'yhtkxjchaennxpdx';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('ni4loupat@gmail.com', $entreprise->getNom());
                $mail->addAddress($email, $employe->getNom());
                $mail->isHTML(true);
                $mail->Subject = 'Votre fiche de paie';
                $mail->Body = 'Bonjour, veuillez trouver en pièce jointe votre fiche de paie.';

                $mail->addAttachment($pathToPdf);
                $mail->send();
            } catch (Exception $e) {
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
