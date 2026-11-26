<?php

namespace App\Controller;

use App\Form\NewPasswordType;
use App\Security\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class PasswordResetController extends AbstractController
{
    #[Route('/zabravena-parola', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        PasswordResetService $reset,
        #[Target('password_reset.limiter')] RateLimiterFactoryInterface $limiter,
    ): Response {
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'submit'])
            ->add('email', EmailType::class, [
                'label' => 'Имейл',
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [new Assert\NotBlank(message: 'Имейлът е задължителен.'), new Assert\Email(message: 'Невалиден имейл адрес.')],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // at most a few e-mails per hour from one address; the answer is the same either way
            if ($limiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
                $reset->sendLink($form->get('email')->getData());
            }

            // Redirect, don't render: Turbo ignores a plain 200 page in answer to a form.
            // The confirmation travels in the address, so no session (and no cookie) is needed.
            return $this->redirectToRoute('app_forgot_password', ['izprateno' => 1], Response::HTTP_SEE_OTHER);
        }

        return $this->render('account/forgot_password.html.twig', [
            'form' => $form,
            'sent' => $request->isMethod('GET') && $request->query->has('izprateno'),
        ]);
    }

    #[Route('/nova-parola/{token}', name: 'app_reset_password', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function reset(string $token, Request $request, PasswordResetService $reset, Security $security): Response
    {
        $user = $reset->findUser($token);
        if (!$user) {
            return $this->render('account/reset_password.html.twig', ['form' => null], new Response(status: Response::HTTP_GONE));
        }

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'submit'])
            ->add('plainPassword', NewPasswordType::class, ['label' => 'Нова парола'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reset->changePassword($user, $form->get('plainPassword')->getData());
            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', 'Паролата е сменена.');

            return $this->redirectToRoute('app_journal');
        }

        return $this->render('account/reset_password.html.twig', ['form' => $form]);
    }
}
