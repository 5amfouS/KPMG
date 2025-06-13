<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use App\Entity\Utilisateur;
use App\Entity\Mdp;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Symfony\Component\Mailer\MailerInterface;


final class LoginController extends AbstractController
{

#[Route('/logout', name: 'app_logout')]
public function logout(Request $request): Response
{
    // Récupérer la session
    $session = $request->getSession();

    // Supprimer toutes les données de la session
    $session->clear();

    // Optionnel : détruire complètement la session
    $session->invalidate();

    // Rediriger vers la page de connexion
    return $this->redirectToRoute('app_signin');
}

#[Route('/mdpoublie', name: 'app_mdpoublie', methods: ['GET', 'POST'])]
public function mdpoublie(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
{
    if ($request->isMethod('POST')) {
        $identifiant = $request->request->get('identifiant');

        // Chercher l'utilisateur par email ou téléphone
        $utilisateur = $em->getRepository(Utilisateur::class)
            ->createQueryBuilder('u')
            ->where('u.email = :id OR u.tel = :id')
            ->setParameter('id', $identifiant)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$utilisateur) {
            $this->addFlash('error', 'Aucun utilisateur trouvé avec cet identifiant.');
            return $this->redirectToRoute('app_mdpoublie');
        }

        // Générer un token unique
        $token = bin2hex(random_bytes(32));
        $utilisateur->setTokenE($token); // Ajoute un champ resetToken dans ton entité
        $em->flush();

        // Générer le lien de réinitialisation

        $mail = new PHPMailer(true);
        $cl = $utilisateur->getTokenE();
        try {
            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'ni4loupat@gmail.com';
            $mail->Password   = 'yhtkxjchaennxpdx';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Destinataire
            $mail->setFrom('ni4loupat@gmail.com', 'KPMG');
            $mail->addAddress($identifiant, $utilisateur->getNom());

            // Contenu
            $mail->isHTML(true);                                        
            $mail->Subject = 'Confirmation de mail';
            $mail->Body = 'Bonjour,<br><br>
                Cliquez sur le lien suivant pour réinitialiser votre mot de passe :<br>
                <a href="http://127.0.0.1:8000/resetmdp/' . $cl . '">Confirmer mon compte</a><br><br>
                Ce lien expirera bientôt.';

            // Envoi de l'email
            $mail->send();
            $this->addFlash('success', 'Un lien de réinitialisation a été envoyé par email..');
            return $this->redirectToRoute('app_signin');

        } catch (Exception $e) {
            return new Response("Erreur lors de l'envoi de l'email: {$mail->ErrorInfo}");
        }
    }

    return $this->render('login/mdpoublie.html.twig');
}

    
#[Route('/signin', name: 'app_signin', methods: ['GET', 'POST'])]
public function login(Request $request, EntityManagerInterface $em): Response
{
    if ($request->isMethod('POST')) {
        $identifiant = $request->request->get('identifiant'); 
        $motDePasse = $request->request->get('mdp');


        $user = $em->getRepository(Utilisateur::class)->createQueryBuilder('u')
            ->where('u.email = :identifiant OR u.tel = :identifiant')
            ->setParameter('identifiant', $identifiant)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('app_signin');
        }


        $mdp = $em->getRepository(Mdp::class)->createQueryBuilder('m')
            ->where('m.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('m.date_creation', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$mdp) {
            $this->addFlash('error', 'Mot de passe introuvable pour cet utilisateur.');
            return $this->redirectToRoute('app_signin');
        }

        // Vérification du mot de passe
        $hasher = new NativePasswordHasher();

        if (!$hasher->verify($mdp->getMdp(), $motDePasse)) {
            $this->addFlash('error', 'Mot de passe incorrect.');
            return $this->redirectToRoute('app_signin');
        }

        if ($user->getStatut() !== 'verifie') {
            $this->addFlash('error', 'Veuillez confirmer votre adresse email avant de vous connecter.');
            return $this->redirectToRoute('app_signin');
        }
        if ($user->getBloque() !== 'non') {
            $this->addFlash('error', 'Votre compte a été suspendu pour des raisons de sécurité, veuillez contacter un administrateur.');
            return $this->redirectToRoute('app_signin');
        }
        else{


        $this->addFlash('success', 'Connexion réussie !');


        
        $session = $request->getSession();
        $session->set('user_id', $user->getId());
        $session->set('user_nom', $user->getNom());
        $session->set('user_role', $user->getRole());
        if ($user->getRole() == 'ADMIN') {
            return $this->redirectToRoute('app_utilisateurs'); 
        }
        else{
            return $this->redirectToRoute('app_mailing');
        }
        }

    }

    return $this->render('login/signin.html.twig');
}


#[Route('/signup', name: 'app_signup', methods: ['GET', 'POST'])]
public function signup(Request $request, EntityManagerInterface $em): Response
{
    if ($request->isMethod('POST')) {
        $nom = $request->request->get('nom');
        $email = $request->request->get('email');
        $tel = $request->request->get('tel');
        $MotDePasse = $request->request->get('mdp');
        $confirmermdp = $request->request->get('confirmermdp');

        if ($MotDePasse !== $confirmermdp) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas');
            return $this->redirectToRoute('app_signup');
        }
        $tokenE = bin2hex(random_bytes(32));
        $user = new Utilisateur();
        $user->setNom($nom);
        $user->setEmail($email);
        $user->setTel($tel);
        $user->setRole('USER');
        $user->setBloque('non');
        $user->setStatut('Non verifie');
        $user->setTokenE($tokenE);
        $user->setTokenS(NULL);

        // Utilisation manuelle du hasher
        $hasher = new NativePasswordHasher();
        $hashedPassword = $hasher->hash($MotDePasse);

        $mdp = new Mdp();
        $mdp->setUtilisateur($user);
        $mdp->setMdp($hashedPassword);
        $mdp->setDateCreation(new \DateTime());

        $em->persist($user);
        $em->persist($mdp);
        $em->flush();
        $mail = new PHPMailer(true);

        try {
            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'ni4loupat@gmail.com';
            $mail->Password   = 'yhtkxjchaennxpdx';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Destinataire
            $mail->setFrom('ni4loupat@gmail.com', 'KPMG');
            $mail->addAddress($user->getEmail(), $user->getNom());

            // Contenu
            $mail->isHTML(true);                                        
            $mail->Subject = 'Confirmation de mail';
            $confirmationLink = 'confirmation/' . $user->getTokenE();
            $mail->Body = 'Veuillez confirmer votre compte en cliquant sur ce lien : 
                <a href="http://127.0.0.1:8000/' . $confirmationLink . '">Confirmer mon compte</a>';

            // Envoi de l'email
            $mail->send();
            $this->addFlash('success', 'Inscription réussie ! Veuillez vérifier votre mail avant de vous connecter.');
            return $this->redirectToRoute('app_signin');

        } catch (Exception $e) {
            return new Response("Erreur lors de l'envoi de l'email: {$mail->ErrorInfo}");
        }







    }

    return $this->render('login/signup.html.twig');
}

    #[Route('/confirmation/{token}', name: 'app_confirmation')]
    public function confirmation(string $token, EntityManagerInterface $em): Response
    {
    // Cherche un utilisateur avec ce token
    $user = $em->getRepository(Utilisateur::class)->findOneBy(['token_e' => $token]);

    if (!$user) {
        $this->addFlash('error', 'Lien de confirmation invalide ou expiré.');
        return $this->redirectToRoute('app_signin');
    }

    // Mise à jour du statut et suppression du token
    $user->setStatut('verifie');
    $user->setTokenE(null);
    $em->flush();

    $this->addFlash('success', 'Votre compte a bien été vérifié ! Vous pouvez maintenant vous connecter.');
    return $this->redirectToRoute('app_signin');
    }



    
    #[Route('/resetmdp/{token}', name: 'app_resetmdp', methods: ['GET', 'POST'])]
public function resetmdp(string $token, Request $request, EntityManagerInterface $em): Response
{
    // 1. Vérifier que le token existe
    $user = $em->getRepository(Utilisateur::class)->findOneBy(['token_e' => $token]);

    if (!$user) {
        $this->addFlash('error', 'Lien invalide ou expiré.');
        return $this->redirectToRoute('app_signin');
    }

    if ($request->isMethod('POST')) {
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_resetmdp', ['token' => $token]);
        }

        // Vérifier que le nouveau mot de passe n’est pas dans les 5 derniers
        $previousPasswords = $em->getRepository(Mdp::class)->createQueryBuilder('m')
            ->where('m.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('m.date_creation', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $hasher = new NativePasswordHasher();

        foreach ($previousPasswords as $oldMdp) {
            if ($hasher->verify($oldMdp->getMdp(), $newPassword)) {
                $this->addFlash('error', 'Vous ne pouvez pas réutiliser un ancien mot de passe.');
                return $this->redirectToRoute('app_resetmdp', ['token' => $token]);
            }
        }

        // Hacher et sauvegarder le nouveau mot de passe
        $hashedPassword = $hasher->hash($newPassword);

        $mdp = new Mdp();
        $mdp->setUtilisateur($user);
        $mdp->setMdp($hashedPassword);
        $mdp->setDateCreation(new \DateTime());

        // Supprimer le token
        $user->setTokenE(null);

        $em->persist($mdp);
        $em->flush();

        $this->addFlash('success', 'Mot de passe modifié avec succès. Vous pouvez maintenant vous connecter.');
        return $this->redirectToRoute('app_signin');
    }

    return $this->render('login/resetmdp.html.twig', [
        'token' => $token
    ]);
}






}
