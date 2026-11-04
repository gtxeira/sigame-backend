<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'rate_limiter' => [
            'auth_login' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_LOGIN_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'auth_social' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_SOCIAL_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'auth_refresh' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_REFRESH_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'user_cpf' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:USER_CPF_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
        ],
    ]);

    if ('test' === $containerConfigurator->env()) {
        $containerConfigurator->extension('framework', [
            'cache' => [
                'pools' => [
                    'cache.rate_limiter' => [
                        'adapter' => 'cache.adapter.filesystem',
                    ],
                ],
            ],
        ]);
    }
};
