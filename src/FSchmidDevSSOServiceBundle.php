<?php

namespace FSchmidDev\SSOServiceBundle;

use FSchmidDev\SSOServiceBundle\Controller\SSOController;
use FSchmidDev\SSOServiceBundle\SSO\UserData;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class FSchmidDevSSOServiceBundle extends AbstractBundle
{
    protected string $extensionAlias = 'fschmid_dev_sso_service';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/sso_service.php');
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');

        $container->services()
            ->get(SSOController::class)
            ->arg('$providerUrl', $config['user_provider']['url'])
            ->arg('$logoutRoute', $config['routes']['logout'])
            ->arg('$loginSuccessRoute', $config['routes']['login_success']);

        $container->services()
            ->get('fschmid_dev_sso_service.event_subscriber.check_user_token_subscriber')
            ->arg('$logoutRoute', $config['routes']['logout']);

        $container->services()
            ->get('fschmid_dev_sso_service.security.authenticator')
            ->arg('$sharedCookieDomain', $config['cookies']['shared_cookie_domain'])
            ->arg('$userJWTCookie', $config['cookies']['user_jwt_cookie'])
            ->arg('$requestUrl', $config['user_provider']['url'])
            ->arg('$userClass', $config['user_provider']['user'])
            ->arg('$loginSuccessRoute', $config['routes']['login_success'])
            ->arg('$loginFailureRoute', $config['routes']['login_failure']);

        $container->services()
            ->get(UserData::class)
            ->arg('$requestUrl', $config['user_provider']['url'])
            ->arg('$userClass', $config['user_provider']['user'])
            ->public();
    }
}
