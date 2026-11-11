<?php

declare(strict_types=1);

use App\Identity\Application\UseCase\CompleteOnboarding\CompleteOnboardingCommand;
use App\Identity\Application\UseCase\CompleteOnboarding\CompleteOnboardingHandler;
use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\OnboardingCompleted;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\OnboardingStatus;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\BirthDate;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Modality;

function completeOnboardingTestUser(bool $withBirthDate = true): User
{
    $user = User::register(
        cpf: null,
        email: Email::fromString('onboarding@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'Onboarding User',
    );

    if ($withBirthDate) {
        $user->assignBirthDate(BirthDate::fromString('1990-05-12')->value());
    }

    return $user;
}

/**
 * @param list<Modality> $modalities
 */
/**
 * @param list<Modality> $modalities
 */
function completeOnboardingTestCommand(
    User $user,
    array $modalities = [Modality::WALKING],
    ?Modality $primaryModality = null,
): CompleteOnboardingCommand {
    return new CompleteOnboardingCommand(
        userId: $user->id(),
        modalities: $modalities,
        primaryModality: $primaryModality,
    );
}

beforeEach(function () {
    $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
    $this->eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $this->handler = new CompleteOnboardingHandler(
        userRepository: $this->userRepository,
        eventDispatcher: $this->eventDispatcher,
    );
});

it('completes onboarding and dispatches the domain event', function () {
    $user = completeOnboardingTestUser();

    $this->userRepository
        ->shouldReceive('findById')
        ->once()
        ->with(Mockery::on(static fn (UserId $id): bool => $id->equals($user->id())))
        ->andReturn($user);

    $this->userRepository
        ->shouldReceive('save')
        ->once()
        ->with(Mockery::on(
            static fn (User $saved): bool => $saved === $user
                && OnboardingStatus::COMPLETED === $saved->onboardingStatus(),
        ));

    $this->eventDispatcher
        ->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(
            static fn (OnboardingCompleted $event): bool => $event->userId() === $user->id()->value(),
        ));

    $result = ($this->handler)(completeOnboardingTestCommand($user));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBe($user)
        ->and($user->onboardingStatus())->toBe(OnboardingStatus::COMPLETED)
        ->and($user->level()->value())->toBe(1)
        ->and($user->modalities())->toBe([Modality::WALKING]);
});

it('completes onboarding without optional profile data', function () {
    $user = completeOnboardingTestUser();

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldReceive('save')->once();
    $this->eventDispatcher->shouldReceive('dispatch')->once();

    $result = ($this->handler)(completeOnboardingTestCommand(
        user: $user,
        modalities: [Modality::CYCLING],
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->height())->toBeNull()
        ->and($user->weight())->toBeNull()
        ->and($user->modalities())->toBe([Modality::CYCLING]);
});

it('rejects onboarding without a birth date', function () {
    $user = completeOnboardingTestUser(withBirthDate: false);

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldNotReceive('save');
    $this->eventDispatcher->shouldNotReceive('dispatch');

    $result = ($this->handler)(completeOnboardingTestCommand($user));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe(IdentityErrorCode::UserBirthDateRequired->value)
        ->and($user->onboardingStatus())->toBe(OnboardingStatus::PENDING)
        ->and($user->modalities())->toBe([]);
});

it('accumulates the missing birth date with the missing modality', function () {
    $user = completeOnboardingTestUser(withBirthDate: false);

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldNotReceive('save');
    $this->eventDispatcher->shouldNotReceive('dispatch');

    $result = ($this->handler)(completeOnboardingTestCommand($user, modalities: []));

    expect($result->isFailure())->toBeTrue()
        ->and(array_map(
            static fn ($error): string => $error->code,
            $result->errors(),
        ))->toBe([
            IdentityErrorCode::UserBirthDateRequired->value,
            IdentityErrorCode::OnboardingNoModalitySelected->value,
        ]);
});

it('throws when the authenticated user does not exist', function () {
    $this->userRepository->shouldReceive('findById')->once()->andReturn(null);
    $this->userRepository->shouldNotReceive('save');
    $this->eventDispatcher->shouldNotReceive('dispatch');

    ($this->handler)(new CompleteOnboardingCommand(
        userId: UserId::generate(),
        modalities: [Modality::WALKING],
        primaryModality: null,
    ));
})->throws(RuntimeException::class, 'Usuário autenticado não encontrado.');

it('rejects onboarding data violating domain rules', function (array $overrides, IdentityErrorCode $code) {
    $user = completeOnboardingTestUser();

    if (true === ($overrides['preCompleted'] ?? false)) {
        $user->completeOnboarding(
            modalities: [Modality::WALKING],
            primaryModality: null,
        );
    }

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldNotReceive('save');
    $this->eventDispatcher->shouldNotReceive('dispatch');

    $modalities = $overrides['modalities'] ?? [Modality::WALKING];
    $primaryModality = $overrides['primaryModality'] ?? null;

    assert(is_array($modalities));
    assert(null === $primaryModality || $primaryModality instanceof Modality);

    $command = new CompleteOnboardingCommand(
        userId: $user->id(),
        modalities: array_values($modalities),
        primaryModality: $primaryModality,
    );

    $result = ($this->handler)($command);

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe($code->value);
})->with([
    'no modality selected' => [
        ['modalities' => []],
        IdentityErrorCode::OnboardingNoModalitySelected,
    ],
    'primary modality not selected' => [
        ['modalities' => [Modality::WALKING], 'primaryModality' => Modality::RUNNING],
        IdentityErrorCode::OnboardingInvalidPrimaryModality,
    ],
    'onboarding already completed' => [
        ['preCompleted' => true],
        IdentityErrorCode::OnboardingAlreadyCompleted,
    ],
]);
