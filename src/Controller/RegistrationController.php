<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/registratsiya', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        Security $security,
        #[Target('registration.limiter')] RateLimiterFactoryInterface $limiter,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_journal');
        }

        $back = SecurityController::localPath($request->query->getString('kam'));
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ('' !== (string) $form->get('website')->getData()) {
                // a bot filled in the hidden field: pretend everything went well
                return $this->redirectToRoute('app_user_login');
            }

            // no e-mail confirmation, so at least slow down mass sign-ups from one address
            if (!$limiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
                $form->addError(new FormError('Твърде много регистрации от това място. Опитай пак след един час.'));
            } else {
                $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
                $em->persist($user);
                $em->flush();

                $security->login($user, 'form_login', 'main');
                $this->addFlash('success', 'Профилът е създаден. Това е твоят дневник.');

                return $this->redirect($back ?? $this->generateUrl('app_journal'));
            }
        }

        return $this->render('account/register.html.twig', ['form' => $form, 'back' => $back]);
    }
}
