<?php

declare(strict_types=1);

use App\Identity\Infrastructure\Security\AccessTokenAuthenticator;
use App\Identity\Infrastructure\Security\AccessTokenEntryPoint;
use App\Identity\Infrastructure\Security\ActiveUserChecker;
use App\Identity\Infrastructure\Security\SecurityUserProvider;
use App\Shared\Presentation\Http\Security\ApiAccessDeniedHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('security', [
        'password_hashers' => [
            PasswordAuthenticatedUserInterface::class => 'auto',
        ],
        'providers' => [
            'app_user_provider' => [
                'id' => SecurityUserProvider::class,
            ],
        ],
        'firewalls' => [
            'dev' => [
                'pattern' => '^/(_profiler|_wdt|assets|build)/',
                'security' => false,
            ],
            'api' => [
                'pattern' => '^/api',
                'stateless' => true,
                'provider' => 'app_user_provider',
                'custom_authenticators' => [
                    AccessTokenAuthenticator::class,
                ],
                'entry_point' => AccessTokenEntryPoint::class,
                'access_denied_handler' => ApiAccessDeniedHandler::class,
                'user_checker' => ActiveUserChecker::class,
            ],
        ],
        'access_control' => [
            ['path' => '^/api/health', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/api/v1/auth/social/[a-z]+/link$', 'roles' => 'ROLE_USER'],
            ['path' => '^/api/v1/auth/email/verification/resend$', 'roles' => 'ROLE_USER'],
            ['path' => '^/api/v1/auth/(register|login|refresh|social|email|password)', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/api/v1/auth/logout$', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/doc', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/api', 'roles' => 'ROLE_USER'],
        ],
    ]);

    if ('test' === $containerConfigurator->env()) {
        $containerConfigurator->extension('security', [
            'password_hashers' => [
                PasswordAuthenticatedUserInterface::class => [
                    'algorithm' => 'auto',
                    'cost' => 4,
                    'time_cost' => 3,
                    'memory_cost' => 10,
                ],
            ],
        ]);
    }
};
