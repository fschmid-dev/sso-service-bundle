<?php

namespace FSchmidDev\SSOServiceBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SSOController extends AbstractController
{
    public function __construct(
        private readonly string $providerUrl,
        private readonly string $logoutRoute,
        private readonly string $loginSuccessRoute
    )
    {
    }

    public function login(Request $request, UrlGeneratorInterface $urlGenerator): Response
    {
        $ssoLoginUrl = sprintf(
            $this->providerUrl . '/login?origin=%s',
            $urlGenerator->generate('app_token', [], UrlGeneratorInterface::ABSOLUTE_URL)
        );

        if ($request->query->count() > 0) {
            foreach ($request->query->all() as $key => $value) {
                $ssoLoginUrl .= sprintf('&%s=%s', $key, $value);
            }
        }

        return new RedirectResponse($ssoLoginUrl);
    }

    public function register(UrlGeneratorInterface $urlGenerator): Response
    {
        return new RedirectResponse(
            sprintf(
                $this->providerUrl . '/register?origin=%s',
                $urlGenerator->generate('app_token', [], UrlGeneratorInterface::ABSOLUTE_URL),
            )
        );
    }

    public function logout(UrlGeneratorInterface $urlGenerator): RedirectResponse
    {
        return new RedirectResponse(
            sprintf(
                $this->providerUrl . '/logout?origin=%s',
                $urlGenerator->generate($this->logoutRoute, [], UrlGeneratorInterface::ABSOLUTE_URL)
            )
        );
    }

    public function account(): RedirectResponse
    {
        return $this->redirect($this->providerUrl . '/account');
    }

    public function token(): Response
    {
        return $this->redirectToRoute($this->loginSuccessRoute);
    }
}
