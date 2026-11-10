<?php

declare(strict_types=1);

use App\Identity\Application\Service\IdentityMailerInterface;
use App\Identity\Infrastructure\Social\GoogleIdentityProvider;
use App\Notifications\Application\Service\RealtimePublisherInterface;
use App\Notifications\Application\Service\StreamAuthorizationProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tests\Support\FakeSocialIdentityProvider;
use Tests\Support\FakeStreamAuthorizationProvider;
use Tests\Support\RecordingIdentityMailer;
use Tests\Support\RecordingRealtimePublisher;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->remove(GoogleIdentityProvider::class);
    $services->set(FakeSocialIdentityProvider::class)
        ->autowire()
        ->autoconfigure();

    $services->set(RecordingIdentityMailer::class)
        ->autowire();
    $services->alias(IdentityMailerInterface::class, RecordingIdentityMailer::class);

    $services->set(RecordingRealtimePublisher::class)
        ->autowire();
    $services->alias(RealtimePublisherInterface::class, RecordingRealtimePublisher::class);

    $services->set(FakeStreamAuthorizationProvider::class)
        ->autowire();
    $services->alias(StreamAuthorizationProviderInterface::class, FakeStreamAuthorizationProvider::class);
};
