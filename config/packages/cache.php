<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'cache' => [
            'pools' => [
                // Fluxos OAuth e tickets de vínculo. Em produção com múltiplas
                // instâncias, este pool precisa apontar para storage compartilhado.
                'cache.social_flow' => [
                    'adapter' => 'cache.app',
                ],
            ],
        ],
    ]);
};
