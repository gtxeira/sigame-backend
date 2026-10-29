<?php

declare(strict_types=1);

use App\Identity\Infrastructure\Persistence\ORM\Type\HashedPasswordType;
use App\Identity\Infrastructure\Persistence\ORM\Type\HeightType;
use App\Identity\Infrastructure\Persistence\ORM\Type\UserIdType;
use App\Identity\Infrastructure\Persistence\ORM\Type\UserLevelType;
use App\Identity\Infrastructure\Persistence\ORM\Type\WeightType;
use App\Shared\Infrastructure\Persistence\ORM\Type\BaseIdType;
use App\Shared\Infrastructure\Persistence\ORM\Type\CpfType;
use App\Shared\Infrastructure\Persistence\ORM\Type\EmailType;
use App\Shared\Infrastructure\Persistence\ORM\Type\InstantType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('doctrine', [
        'dbal' => [
            'url' => '%env(resolve:DATABASE_URL)%',
            'profiling_collect_backtrace' => '%kernel.debug%',
            'mapping_types' => [
                'timestamptz' => 'instant',
            ],
            'types' => [
                'base_id' => BaseIdType::class,
                'user_id' => UserIdType::class,
                'email' => EmailType::class,
                'cpf' => CpfType::class,
                'hashed_password' => HashedPasswordType::class,
                'user_level' => UserLevelType::class,
                'height' => HeightType::class,
                'weight' => WeightType::class,
                'instant' => InstantType::class,
            ],
        ],
        'orm' => [
            'validate_xml_mapping' => true,
            'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
            'identity_generation_preferences' => [
                PostgreSQLPlatform::class => 'identity',
            ],
            'auto_mapping' => false,
            'mappings' => [
                'Identity' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Identity/Domain',
                    'prefix' => 'App\Identity\Domain',
                    'alias' => 'Identity',
                ],
            ],
        ],
    ]);

    if ($containerConfigurator->env() === 'test') {
        $containerConfigurator->extension('doctrine', [
            'dbal' => [
                'dbname_suffix' => '_test%env(default::TEST_TOKEN)%',
            ],
        ]);
    }

    if ($containerConfigurator->env() === 'prod') {
        $containerConfigurator->extension('doctrine', [
            'orm' => [
                'query_cache_driver' => [
                    'type' => 'pool',
                    'pool' => 'doctrine.system_cache_pool',
                ],
                'result_cache_driver' => [
                    'type' => 'pool',
                    'pool' => 'doctrine.result_cache_pool',
                ],
            ],
        ]);

        $containerConfigurator->extension('framework', [
            'cache' => [
                'pools' => [
                    'doctrine.result_cache_pool' => [
                        'adapter' => 'cache.app',
                    ],
                    'doctrine.system_cache_pool' => [
                        'adapter' => 'cache.system',
                    ],
                ],
            ],
        ]);
    }
};
