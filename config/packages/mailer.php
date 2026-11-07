<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'mailer' => [
            'dsn' => '%env(MAILER_DSN)%',
            // O worker já envia dentro do handler; usar o bus aqui só criaria
            // uma segunda camada de mensageria para o mesmo e-mail.
            'message_bus' => false,
        ],
    ]);
};
