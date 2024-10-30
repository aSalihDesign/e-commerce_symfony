<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\SecurityAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $userPasswordHasher, 
        Security $security, 
        EntityManagerInterface $entityManager
    ): Response {
        // Rediriger si déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('shop');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Définir le rôle CLIENT par défaut
            $user->setRoles(['ROLE_CLIENT']);

            // Encoder le mot de passe
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            // Connecter l'utilisateur et rediriger selon le SecurityAuthenticator
            $response = $security->login($user, SecurityAuthenticator::class, 'main');
            
            // Le SecurityAuthenticator va gérer la redirection en fonction du panier
            return $response;
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
            'hasCart' => !empty($request->getSession()->get('cart', [])),
        ]);
    }
}