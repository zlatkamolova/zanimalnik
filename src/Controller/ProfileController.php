<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\NewPasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;

/** Name, password and "delete my account". Needs a login (see security.yaml). */
final class ProfileController extends AbstractController
{
    /** The same stateless CSRF token as every other form of the site (see config/packages/csrf.yaml). */
    private const STATELESS_CSRF = ['csrf_token_id' => 'submit'];

    #[Route('/profil', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, Security $security): Response
    {
        $name = $this->container->get('form.factory')->createNamedBuilder('name', FormType::class, $user, self::STATELESS_CSRF)
            ->add('displayName', TextType::class, ['label' => 'Име', 'required' => false, 'attr' => ['autocomplete' => 'nickname']])
            ->getForm();

        $password = $this->container->get('form.factory')->createNamedBuilder('password', options: self::STATELESS_CSRF)
            ->add('current', PasswordType::class, $this->currentPassword())
            ->add('new', NewPasswordType::class, ['label' => 'Нова парола'])
            ->getForm();

        // an administrator is removed by another administrator, never by accident from here
        $delete = $user->isAdmin() ? null : $this->container->get('form.factory')->createNamedBuilder('delete', options: self::STATELESS_CSRF)
            ->add('current', PasswordType::class, $this->currentPassword())
            ->getForm();

        if ($this->submitted($name, $request)) {
            $em->flush();
            $this->addFlash('success', 'Името е записано.');

            return $this->redirectToRoute('app_profile');
        }

        if ($this->submitted($password, $request)) {
            $user->setPassword($hasher->hashPassword($user, $password->get('new')->getData()));
            $em->flush();
            // the session remembers the old password; log in again so the visitor stays in
            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', 'Паролата е сменена.');

            return $this->redirectToRoute('app_profile');
        }

        if ($delete && $this->submitted($delete, $request)) {
            $security->logout(false);
            $em->remove($user); // the journal goes with it (cascade)
            $em->flush();

            return $this->redirectToRoute('app_home');
        }

        return $this->render('account/profile.html.twig', [
            'name_form' => $name,
            'password_form' => $password,
            'delete_form' => $delete,
        ]);
    }

    private function submitted(FormInterface $form, Request $request): bool
    {
        $form->handleRequest($request);

        return $form->isSubmitted() && $form->isValid();
    }

    private function currentPassword(): array
    {
        return [
            'label' => 'Текуща парола',
            'attr' => ['autocomplete' => 'current-password'],
            'constraints' => [new Assert\NotBlank(message: 'Напиши текущата си парола.'), new UserPassword(message: 'Текущата парола не е вярна.')],
        ];
    }
}
