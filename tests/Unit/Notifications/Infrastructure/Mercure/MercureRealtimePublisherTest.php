<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Infrastructure\Mercure;

use App\Notifications\Application\Service\RealtimeNotificationEvent;
use App\Notifications\Infrastructure\Mercure\MercureRealtimePublisher;
use Mockery as m;
use Psr\Log\NullLogger;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

it('publishes a private update on the recipient topic', function () {
    $captured = null;
    $hub = m::mock(HubInterface::class);
    $hub->shouldReceive('publish')->once()->andReturnUsing(function (Update $update) use (&$captured): string {
        $captured = $update;

        return 'id';
    });

    $publisher = new MercureRealtimePublisher(
        hub: $hub,
        logger: new NullLogger(),
        topicBase: 'https://mercure.test/notifications',
    );

    $publisher->publish('user-1', new RealtimeNotificationEvent(
        event: 'notification.created',
        data: ['unreadCount' => 2],
    ));

    expect($captured)->not->toBeNull()
        ->and($captured->getTopics())->toBe(['https://mercure.test/notifications/user-1'])
        ->and($captured->isPrivate())->toBeTrue()
        ->and($captured->getType())->toBe('notification.created')
        ->and(json_decode($captured->getData(), true))->toBe(['unreadCount' => 2]);
});
