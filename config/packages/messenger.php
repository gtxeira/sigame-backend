<?php

declare(strict_types=1);

use App\Identity\Infrastructure\Mailer\Message\SendEmailVerificationMessage;
use App\Identity\Infrastructure\Mailer\Message\SendPasswordResetMessage;
use App\Notifications\Application\Message\CreateNotificationsMessage;
use App\Notifications\Application\Message\DeliverNotificationMessage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'messenger' => [
            'failure_transport' => 'failed',
            'transports' => [
                'async' => [
                    'dsn' => '%env(MESSENGER_TRANSPORT_DSN)%',
                    'retry_strategy' => [
                        'max_retries' => 3,
                        'delay' => 1000,
                        'multiplier' => 2,
                        'max_delay' => 10000,
                    ],
                ],
                'failed' => 'doctrine://default?queue_name=failed',
            ],
            'routing' => [
                SendEmailVerificationMessage::class => 'async',
                SendPasswordResetMessage::class => 'async',
                CreateNotificationsMessage::class => 'async',
                DeliverNotificationMessage::class => 'async',
            ],
        ],
    ]);
};
