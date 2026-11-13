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
            'user_birth_date' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:USER_BIRTH_DATE_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'user_onboarding' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:USER_ONBOARDING_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'user_photo' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:USER_PHOTO_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'user_profile' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:USER_PROFILE_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'group_create' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:GROUP_CREATE_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'group_membership_request' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:GROUP_MEMBERSHIP_REQUEST_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'group_membership_leave' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:GROUP_MEMBERSHIP_LEAVE_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'group_manage' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:GROUP_MANAGE_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'activity_create' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:ACTIVITY_CREATE_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'activity_manage' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:ACTIVITY_MANAGE_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'activity_enroll' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:ACTIVITY_ENROLL_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'gamification_read' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:GAMIFICATION_READ_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'gamification_write' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:GAMIFICATION_WRITE_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'notifications_read' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:NOTIFICATIONS_READ_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'notifications_write' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:NOTIFICATIONS_WRITE_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'notifications_realtime' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:NOTIFICATIONS_REALTIME_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'admin_users_read' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:ADMIN_USERS_READ_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
            'admin_users_manage' => [
                'policy' => 'sliding_window',
                'limit' => '%env(int:ADMIN_USERS_MANAGE_RATE_LIMIT)%',
                'interval' => '1 minute',
            ],
        ],
    ]);

    if ('test' === $containerConfigurator->env()) {
        $containerConfigurator->extension('framework', [
            'cache' => [
                'pools' => [
                    // APCu sobrevive ao reset de serviços entre requisições do
                    // mesmo teste e é limpo no beforeEach. Um ArrayAdapter seria
                    // zerado a cada requisição pelo services_resetter.
                    'cache.rate_limiter' => [
                        'adapter' => 'cache.adapter.apcu',
                    ],
                ],
            ],
        ]);
    }
};
