<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'secret' => '%env(APP_SECRET)%',
        'session' => true,
    ]);

    if ($containerConfigurator->env() === 'dev') {
        // O hub do Mercure no FrankenPHP usa o CA interno do Caddy. O publish interno
        // passa pelo endereço público (`api.sigame.localhost`), cujo certificado não
        // está no trust store do container. Só no dev: em produção o certificado é válido.
        $containerConfigurator->extension('framework', [
            'http_client' => [
                'default_options' => [
                    'verify_peer' => false,
                    'verify_host' => false,
                ],
            ],
        ]);
    }

    if ($containerConfigurator->env() === 'test') {
        $containerConfigurator->extension('framework', [
            'test' => true,
            'session' => [
                'storage_factory_id' => 'session.storage.factory.mock_file',
            ],
        ]);
    }
};
