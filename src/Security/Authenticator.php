<?php

namespace FSchmidDev\SSOServiceBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use FSchmidDev\RedirectExceptionBundle\Exception\RedirectException;
use FSchmidDev\SSOServiceBundle\SSO\UserData;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class Authenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    private ?string $token = null;

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly JWTEncoderInterface $JWTEncoder,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserData $userData,
        private readonly string $sharedCookieDomain,
        private readonly string $userJWTCookie,
        private readonly string $requestUrl,
        private readonly string $userClass,
        private readonly string $loginSuccessRoute,
        private readonly string $loginFailureRoute
    )
    {
    }

    public function supports(Request $request): ?bool
    {
        if ($this->security->isGranted('IS_AUTHENTICATED_FULLY')) {
            return false;
        }

        $token = $this->getJWT($request);
        if ($token) {
            return true;
        }

        return false;
    }

    public function authenticate(Request $request): Passport
    {
        return new SelfValidatingPassport(
            new UserBadge(
                $this->getJWT($request),
                function ($token) {
                    try {
                        $data = $this->JWTEncoder->decode($token);
                    } catch (JWTDecodeFailureException $exception) {
                        $response = new RedirectResponse($this->urlGenerator->generate('app_login', [
                            'redirectType' => 'error',
                            'redirectReason' => sprintf('jwt.%s', $exception->getReason())
                        ]));

                        // Clear cookie
                        $this->clearCookie($response);

                        throw new RedirectException($response);
                    }

                    $username = $data['username'];
                    $user = $this->entityManager->getRepository($this->userClass)->findOneBy([
                        'username' => $username
                    ]);

                    return $this->userData->fetchAndMerge($user, $token);
                }
            )
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            $url = $targetPath;
        } else {
            $url = $this->urlGenerator->generate($this->loginSuccessRoute);
        }

        return new RedirectResponse($url);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        }

        $response = new RedirectResponse($this->urlGenerator->generate($this->loginFailureRoute));

        $this->clearCookie($response);

        return $response;
    }

    private function clearCookie(Response $response): void
    {
        $response->headers->setCookie(
            Cookie::create($this->userJWTCookie)
                ->withValue(null)
                ->withDomain($this->sharedCookieDomain)
        );
    }

    private function getJWT(Request $request): ?string
    {
        if (null === $this->token) {
            $this->token = $request->cookies->get($this->userJWTCookie);
        }
        return $this->token;
    }
}
