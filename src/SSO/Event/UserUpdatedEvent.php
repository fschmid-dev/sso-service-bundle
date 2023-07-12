<?php

namespace FSchmidDev\SSOServiceBundle\SSO\Event;

class UserUpdatedEvent extends UserEvent
{
    public const NAME = 'fschmid.sso.user.updated';
}
