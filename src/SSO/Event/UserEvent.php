<?php

namespace FSchmidDev\SSOServiceBundle\SSO\Event;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class UserEvent extends Event
{
    public const NAME = 'fschmid.sso.user';

    public function __construct(protected UserInterface $user)
    {
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }
}
