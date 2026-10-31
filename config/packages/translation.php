<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'default_locale' => 'pt_BR',
        'enabled_locales' => ['pt_BR'],
        'set_locale_from_accept_language' => false,
        'translator' => [
            'default_path' => '%kernel.project_dir%/translations',
            'fallbacks' => ['pt_BR'],
        ],
    ]);
};
