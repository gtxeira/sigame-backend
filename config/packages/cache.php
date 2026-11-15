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
                // Cooldown por e-mail dos pedidos de reset de senha. A chave é
                // o hash do e-mail, inclusive para endereços sem conta.
                'cache.password_reset_throttle' => [
                    'adapter' => 'cache.app',
                ],
                // Cooldown do aviso "conta já existe" enviado no cadastro.
                'cache.account_exists_notice' => [
                    'adapter' => 'cache.app',
                ],
            ],
        ],
    ]);
};
