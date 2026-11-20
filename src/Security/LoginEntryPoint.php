<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/** Anonymous visitors go to the site login (/vhod); anything under /admin goes to the admin login. */
final class LoginEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $route = str_starts_with($request->getPathInfo(), '/admin') ? 'app_login' : 'app_user_login';

        return new RedirectResponse($this->urls->generate($route));
    }
}
