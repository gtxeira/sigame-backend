<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mercure\ProtocolVersion;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('mercure', [
        'hubs' => [
            'default' => [
                'url' => '%env(default::MERCURE_URL)%',
                'public_url' => '%env(default::MERCURE_PUBLIC_URL)%',
                // O hub embutido no FrankenPHP/Caddy usa o protocolo legado 0.x
                // (ver protocol_version_compatibility no Caddyfile).
                'protocol_version' => ProtocolVersion::Legacy,
                'jwt' => [
                    'secret' => '%env(MERCURE_JWT_SECRET)%',
                    'publish' => '*',
                    'claims' => [
                        'iss' => '%env(MERCURE_JWT_ISSUER)%',
                        'sub' => 'app',
                        'client_id' => 'app',
                    ],
                ],
            ],
        ],
    ]);
};
