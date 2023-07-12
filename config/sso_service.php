<?php

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

return static function (DefinitionConfigurator $definition) {
    $definition->rootNode()
        ->children()
            ->arrayNode('cookies')->info('Configuration for the SSO service cookies')
                ->children()
                    ->scalarNode('shared_cookie_domain')->isRequired()->cannotBeEmpty()->defaultNull()->info('The domain, on which the cookies are shared. Usually something like .example.com (leading . is important)')->end()
                    ->scalarNode('user_jwt_cookie')->defaultValue('USER_JWT')->info('The name for the cookie, that stores the JWT')->end()
                ->end()
            ->end()
            ->arrayNode('user_provider')->info('Configuration for the JWT user loader')
                ->children()
                    ->scalarNode('url')->isRequired()->defaultNull()->info('The URL to request user data from the User Server')->end()
                    ->scalarNode('user')->isRequired()->defaultNull()->info('The User class from your project, that the user provider should support')->end()
                ->end()
            ->end()
            ->arrayNode('routes')->info('Configuration for redirect routes')
                ->children()
                    ->scalarNode('login_success')->isRequired()->defaultNull('app_home')->info('The redirect route after successful login.')->end()
                    ->scalarNode('login_failure')->isRequired()->defaultNull('app_home')->info('The redirect route after failed login.')->end()
                    ->scalarNode('logout')->isRequired()->defaultNull('app_local_logout')->info('The normal logout route for the application.')->end()
                ->end()
            ->end()
        ->end()
    ;
};
