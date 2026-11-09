<?php

declare(strict_types=1);

use App\Shared\Application\Storage\FileStorageInterface;
use App\Shared\Infrastructure\Storage\FlysystemFileStorage;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    if ('test' === $containerConfigurator->env()) {
        $services->set('storage.adapter', LocalFilesystemAdapter::class)
            ->args(['%kernel.project_dir%/var/storage-test']);
    } else {
        $services->set(S3Client::class)
            ->args([[
                'version' => 'latest',
                'region' => '%env(S3_REGION)%',
                'endpoint' => '%env(S3_ENDPOINT)%',
                'use_path_style_endpoint' => '%env(bool:S3_PATH_STYLE)%',
                'signature_version' => 'v4',
                'credentials' => [
                    'key' => '%env(S3_ACCESS_KEY)%',
                    'secret' => '%env(S3_SECRET_KEY)%',
                ],
            ]]);

        $services->set('storage.adapter', AwsS3V3Adapter::class)
            ->args([service(S3Client::class), '%env(S3_BUCKET)%']);
    }

    $services->set('storage.filesystem', Filesystem::class)
        ->args([service('storage.adapter')]);

    $services->alias(FileStorageInterface::class, FlysystemFileStorage::class);
};
