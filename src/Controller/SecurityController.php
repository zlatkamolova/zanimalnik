<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class SecurityController extends AbstractController
{
    use TargetPathTrait;

    /** Admin login (EasyAdmin look). Also the address both login forms post to. */
    #[Route('/admin/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin');
        }

        return $this->render('admin/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
            'translation_domain' => 'admin',
            'page_title' => 'Занималник · вход',
            'csrf_token_intention' => 'authenticate',
            'target_path' => $this->generateUrl('admin'),
            'username_label' => 'Имейл',
            'password_label' => 'Парола',
            'sign_in_label' => 'Вход',
            'remember_me_enabled' => false,
        ]);
    }

    /**
     * Visitor login, in the site's look. After logging in the visitor returns to "?kam=" (a page of
     * this site), or to the protected page that sent them here, or to the journal.
     */
    #[Route('/vhod', name: 'app_user_login', methods: ['GET'])]
    public function userLogin(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_journal');
        }

        $back = self::localPath($request->query->getString('kam'));
        $saved = $request->hasPreviousSession() ? $this->getTargetPath($request->getSession(), 'main') : null;

        return $this->render('account/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
            'back' => $back,
            'target_path' => $back ?? $saved ?? $this->generateUrl('app_journal'),
        ]);
    }

    #[Route('/izhod', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Handled by the firewall.');
    }

    /** Only paths of this site ("/kuest/..."), never another host. */
    public static function localPath(string $path): ?string
    {
        return preg_match('#^/(?![/\\\\])[^\s\\\\]*$#', $path) ? $path : null;
    }
}
