<?php

namespace FSchmidDev\SSOServiceBundle\SSO\Event;

use Symfony\Component\Security\Core\User\UserInterface;

class UserCreatedEvent extends UserEvent
{
    public const NAME = 'fschmid.sso.user.created';
}
