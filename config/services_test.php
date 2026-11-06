<?php

declare(strict_types=1);

use App\Identity\Application\Service\IdentityMailerInterface;
use App\Identity\Infrastructure\Social\GoogleIdentityProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tests\Support\FakeSocialIdentityProvider;
use Tests\Support\RecordingIdentityMailer;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->remove(GoogleIdentityProvider::class);
    $services->set(FakeSocialIdentityProvider::class)
        ->autowire()
        ->autoconfigure();

    $services->set(RecordingIdentityMailer::class)
        ->autowire();
    $services->alias(IdentityMailerInterface::class, RecordingIdentityMailer::class);
};
