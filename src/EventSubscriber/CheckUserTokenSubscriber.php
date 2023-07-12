<?php

namespace FSchmidDev\SSOServiceBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use FSchmidDev\SSOServiceBundle\SSO\UserData;
use JetBrains\PhpStorm\ArrayShape;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CheckUserTokenSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly JWTEncoderInterface $JWTEncoder,
        private readonly UserData $userData,
        private readonly string $logoutRoute
    )
    {
    }

    #[ArrayShape([KernelEvents::REQUEST => "string"])]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onRequest'
        ];
    }

    public function onRequest(RequestEvent $requestEvent)
    {
        $request = $requestEvent->getRequest();
        if ($request->attributes->get('_route') === '_wdt') {
            return;
        }

        if (in_array(
            $request->attributes->get('_route'),
            ['app_login', 'app_register', 'app_logout', 'app_token']
        )) {
            return;
        }

        /*
        if (str_starts_with($request->attributes->get('_route'), 'fschmid_dev_sso_service_')) {
            return;
        }
        */

        $user = $this->security->getUser();
        if (!$user) {
            return;
        }

        $token = $request->cookies->get('USER_JWT');
        if (!$token) {
            // we have a user, but not a shared jwt domain
            //  user has logged out in another service, so we need to log him out here as well
            $this->security->logout(false);

            /*
            $requestEvent->setResponse(
                new RedirectResponse($this->urlGenerator->generate('app_login'))
            );
            */

            return;
        }

        // check if user and token match
        try {
            $data = $this->JWTEncoder->decode($token);
        } catch (\Throwable $e) {
            // Something went wrong while decoding the JWT
            //  then just log the user out
            $requestEvent->setResponse(
                new RedirectResponse($this->urlGenerator->generate($this->logoutRoute))
            );

            return;
        }

        // Check if session user and JWT user match
        if ($user->getUserIdentifier() === $data['username']) {
            // session and token match
            //  check if user was upated in comparision to the saved user
            if (!isset($data['updatedAt'])) {
                return;
            }

            $tokenUpdatedAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $data['updatedAt']);

            if ($user->getUpdatedAt() < $tokenUpdatedAt) {
                $this->userData->fetchAndMerge($user, $token);
            }

            return;
        }

        // Session and token don't match
        $this->security->logout(false);

        $requestEvent->setResponse(
            new RedirectResponse($this->urlGenerator->generate('app_login'))
        );
    }
}
