<?php

declare(strict_types=1);

use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\ValueObject\ActivityDescription;
use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityRoute;
use App\Activities\Domain\Activity\ValueObject\ActivityTitle;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Domain\Activity\ValueObject\ActivityWindow;
use App\Activities\Domain\Activity\ValueObject\EstimatedDistance;
use App\Activities\Domain\Activity\ValueObject\MeetingPoint;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\HttpFoundation\Response;

it('updates, reschedules and lists the activity history', function () {
    $context = activityTestGroupWithMembers($this, 'activities-update');
    $activity = activityTestCreate($this, $context['adminToken'], $context['groupId']);

    $response = $this->api->patch(
        'app_activity_update',
        [
            'title' => 'Corrida remarcada',
            'startsAt' => (new DateTimeImmutable('+4 hours'))->format(DateTimeInterface::RFC3339_EXTENDED),
            'estimatedDurationMinutes' => 90,
            'reason' => 'Mudança de horário',
        ],
        ['id' => $activity['id']],
        groupTestServer($context['adminToken']),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($response)['data']['title'])->toBe('Corrida remarcada')
        ->and(authTestBody($response)['data']['estimatedDurationMinutes'])->toBe(90);

    $history = $this->api->get(
        'app_activity_reschedule_list',
        [],
        ['id' => $activity['id']],
        groupTestServer($context['adminToken']),
    );

    expect($history->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($history)['data'])->toHaveCount(1)
        ->and(authTestBody($history)['data'][0]['reason'])->toBe('Mudança de horário');
});

it('reports every invalid update field together', function () {
    $context = activityTestGroupWithMembers($this, 'activities-update-multi-error');
    $activity = activityTestCreate($this, $context['adminToken'], $context['groupId']);

    $response = $this->api->patch(
        'app_activity_update',
        [
            'title' => 'ab',
            'startsAt' => 'ontem',
            'meetingPoint' => 'x',
        ],
        ['id' => $activity['id']],
        groupTestServer($context['adminToken']),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe([
            'STRING_TOO_SHORT',
            'STRING_TOO_SHORT',
            'INVALID_FORMAT',
        ]);
});

it('cancels and invalidates activities through the lifecycle endpoints', function () {
    $context = activityTestGroupWithMembers($this, 'activities-lifecycle');
    $activity = activityTestCreate($this, $context['adminToken'], $context['groupId'], [
        'startsAt' => (new DateTimeImmutable('+5 minutes'))->format(DateTimeInterface::RFC3339_EXTENDED),
        'estimatedDurationMinutes' => 15,
    ]);

    $start = $this->api->post(
        'app_activity_start',
        [],
        ['id' => $activity['id']],
        groupTestServer($context['adminToken']),
    );
    expect($start->getStatusCode())->toBe(Response::HTTP_OK);

    $finish = $this->api->post(
        'app_activity_finish',
        [],
        ['id' => $activity['id']],
        groupTestServer($context['adminToken']),
    );
    expect($finish->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($finish)['data']['status'])->toBe('finished');

    $invalidate = $this->api->post(
        'app_activity_invalidate',
        ['reason' => 'Registro duplicado'],
        ['id' => $activity['id']],
        groupTestServer($context['adminToken']),
    );
    expect($invalidate->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($invalidate)['data']['status'])->toBe('invalidated');

    $other = activityTestCreate($this, $context['adminToken'], $context['groupId']);

    $cancel = $this->api->post(
        'app_activity_cancel',
        ['reason' => 'Chuva forte'],
        ['id' => $other['id']],
        groupTestServer($context['adminToken']),
    );

    expect($cancel->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($cancel)['data']['status'])->toBe('cancelled');
});

it('validates attendance after finish and lists my activities', function () {
    $context = activityTestGroupWithMembers($this, 'activities-attendance');
    $memberEmails = array_keys($context['members']);
    $firstEmail = $memberEmails[0];
    $secondEmail = $memberEmails[1];
    $firstToken = $context['members'][$firstEmail];

    $startsAt = Instant::fromDateTime(new DateTimeImmutable('-3 hours'));
    $window = ActivityWindow::fromStart($startsAt, 60)->value();
    $adminId = ActivityUserId::fromString($context['userIds']['activities-attendance-admin@example.com']);

    $activity = Activity::create(
        groupId: ActivityGroupId::fromString($context['groupId']),
        createdBy: $adminId,
        title: ActivityTitle::fromString('Corrida encerrada')->value(),
        description: ActivityDescription::fromString('Atividade para validação')->value(),
        window: $window,
        meetingPoint: MeetingPoint::fromString('Portão principal')->value(),
        route: ActivityRoute::fromString('Volta completa')->value(),
        estimatedDistance: EstimatedDistance::fromMeters(3000)->value(),
    );

    $enrolledAt = Instant::fromDateTime(new DateTimeImmutable('-4 hours'));
    $firstUser = ActivityUserId::fromString($context['userIds'][$firstEmail]);
    $secondUser = ActivityUserId::fromString($context['userIds'][$secondEmail]);
    $activity->enroll($firstUser, $enrolledAt);
    $activity->enroll($secondUser, $enrolledAt);
    $activity->start($adminId, $startsAt);
    $checkInAt = Instant::fromDateTime($startsAt->value()->modify('+10 minutes'));
    $activity->checkIn($firstUser, $checkInAt);
    $activity->checkIn($secondUser, $checkInAt);
    $activity->finish($adminId, Instant::fromDateTime($startsAt->value()->modify('+70 minutes')));

    /* @phpstan-ignore-next-line */
    $this->api->getKernelBrowser()->getContainer()
        ->get(ActivityRepositoryInterface::class)
        ->save($activity);

    $attendances = $this->api->get(
        'app_activity_attendance_list',
        [],
        ['id' => $activity->id()->value()],
        groupTestServer($context['adminToken']),
    );

    expect($attendances->getStatusCode())->toBe(Response::HTTP_OK);

    $attendanceItems = authTestBody($attendances)['data'];
    expect($attendanceItems)->toHaveCount(2);

    $attendanceId = $attendanceItems[0]['id'];

    $validate = $this->api->post(
        'app_activity_attendance_validate',
        [],
        ['id' => $activity->id()->value(), 'attendanceId' => $attendanceId],
        groupTestServer($context['adminToken']),
    );

    expect($validate->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($validate)['data']['status'])->toBe('validated');

    $again = $this->api->post(
        'app_activity_attendance_validate',
        [],
        ['id' => $activity->id()->value(), 'attendanceId' => $attendanceId],
        groupTestServer($context['adminToken']),
    );

    expect($again->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($again)['data']['id'])->toBe($attendanceId);

    $validatedUserId = authTestBody($validate)['data']['userId'];
    $validatedEmail = array_search($validatedUserId, $context['userIds'], true);
    expect($validatedEmail)->toBeString();

    $progress = $this->api->get(
        'app_my_progress',
        server: groupTestServer($context['members'][$validatedEmail]),
    );
    $progressData = authTestBody($progress)['data'];

    expect($progressData['xpBalance'])->toBeGreaterThanOrEqual(145)
        ->and($progressData['xpLifetime'])->toBeGreaterThanOrEqual(145)
        ->and($progressData['weeklyActivities'])->toBe(1)
        ->and($progressData['currentWeeklyStreak'])->toBe(1)
        ->and($progressData['streakState'])->toBe('ACESO');

    ledgerTestAssertProjectionConsistent($this, $validatedUserId);

    $history = $this->api->get(
        'app_my_xp_history',
        server: groupTestServer($context['members'][$validatedEmail]),
    );
    /** @var list<array<string, mixed>> $historyItems */
    $historyItems = authTestBody($history)['data'];
    $activityMetadata = [];
    $weeklyStartMetadata = [];

    foreach ($historyItems as $item) {
        $metadata = $item['metadata'] ?? null;

        if ('activity' === $item['kind'] && is_array($metadata)) {
            $activityMetadata = $metadata;
        }

        if ('weekly_start' === $item['kind'] && is_array($metadata)) {
            $weeklyStartMetadata = $metadata;
        }
    }

    expect($activityMetadata)->toHaveKeys([
        'modality',
        'distanceMeters',
        'durationMinutes',
        'checkedInParticipants',
        'startsAt',
        'dailySequence',
        'calculatedAmount',
    ])
        ->and($activityMetadata['modality'])->toBe('corrida')
        ->and($activityMetadata['dailySequence'])->toBe(1)
        ->and($weeklyStartMetadata['weeklyCount'])->toBe(1);

    $invalidate = $this->api->post(
        'app_activity_invalidate',
        ['reason' => 'Atividade registrada em duplicidade.'],
        ['id' => $activity->id()->value()],
        groupTestServer($context['adminToken']),
    );

    expect($invalidate->getStatusCode())->toBe(Response::HTTP_OK);

    $reversedProgress = $this->api->get(
        'app_my_progress',
        server: groupTestServer($context['members'][$validatedEmail]),
    );
    $reversedData = authTestBody($reversedProgress)['data'];

    expect($reversedData['xpBalance'])->toBe(100)
        ->and($reversedData['xpLifetime'])->toBe(100)
        ->and($reversedData['weeklyActivities'])->toBe(0)
        ->and($reversedData['currentWeeklyStreak'])->toBe(0)
        ->and($reversedData['streakState'])->toBe('APAGADO');

    ledgerTestAssertProjectionConsistent($this, $validatedUserId);

    $mine = $this->api->get('app_my_activity_list', server: groupTestServer($firstToken));

    expect($mine->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($mine)['data'])->toHaveCount(1)
        ->and(authTestBody($mine)['data'][0]['id'])->toBe($activity->id()->value());
});
