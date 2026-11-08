<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'rate_limiter' => [
            'auth_register' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_REGISTER_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'auth_login' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_LOGIN_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'auth_social_start' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_SOCIAL_START_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'auth_social_callback' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_SOCIAL_CALLBACK_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'auth_social_link' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_SOCIAL_LINK_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'auth_email_verify' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_EMAIL_VERIFY_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'auth_email_resend' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_EMAIL_RESEND_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'auth_password_forgot' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_PASSWORD_FORGOT_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'auth_password_reset' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_PASSWORD_RESET_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'auth_password_change' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:AUTH_PASSWORD_CHANGE_RATE_LIMIT)%',
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
            'user_onboarding' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:USER_ONBOARDING_RATE_LIMIT)%',
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
