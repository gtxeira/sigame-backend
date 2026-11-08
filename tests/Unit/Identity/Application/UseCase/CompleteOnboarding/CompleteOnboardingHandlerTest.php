<?php

declare(strict_types=1);

use App\Identity\Application\UseCase\CompleteOnboarding\CompleteOnboardingCommand;
use App\Identity\Application\UseCase\CompleteOnboarding\CompleteOnboardingHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\OnboardingCompleted;
use App\Identity\Domain\User\Exception\InvalidHeightException;
use App\Identity\Domain\User\Exception\InvalidWeightException;
use App\Identity\Domain\User\Exception\OnboardingAlreadyCompletedException;
use App\Identity\Domain\User\Exception\OnboardingInvalidPrimaryModalityException;
use App\Identity\Domain\User\Exception\OnboardingNoModalitySelectedException;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\OnboardingStatus;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Modality;

function completeOnboardingTestUser(): User
{
    return User::register(
        cpf: null,
        email: Email::fromString('onboarding@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'Onboarding User',
    );
}

/**
 * @param list<Modality> $modalities
 */
function completeOnboardingTestCommand(
    User $user,
    ?int $heightCm = 178,
    ?float $weightKg = 75.5,
    array $modalities = [Modality::WALKING],
    ?Modality $primaryModality = null,
): CompleteOnboardingCommand {
    return new CompleteOnboardingCommand(
        userId: $user->id(),
        heightCm: $heightCm,
        weightKg: $weightKg,
        photoUrl: null,
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
        ->and($user->level()->value())->toBe(2)
        ->and($user->height()?->centimeters())->toBe(178)
        ->and($user->weight()?->kilograms())->toBe(75.5)
        ->and($user->modalities())->toBe([Modality::WALKING]);
});

it('completes onboarding without optional profile data', function () {
    $user = completeOnboardingTestUser();

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldReceive('save')->once();
    $this->eventDispatcher->shouldReceive('dispatch')->once();

    $result = ($this->handler)(completeOnboardingTestCommand(
        user: $user,
        heightCm: null,
        weightKg: null,
        modalities: [Modality::CYCLING],
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->height())->toBeNull()
        ->and($user->weight())->toBeNull()
        ->and($user->modalities())->toBe([Modality::CYCLING]);
});

it('throws when the authenticated user does not exist', function () {
    $this->userRepository->shouldReceive('findById')->once()->andReturn(null);
    $this->userRepository->shouldNotReceive('save');
    $this->eventDispatcher->shouldNotReceive('dispatch');

    ($this->handler)(new CompleteOnboardingCommand(
        userId: UserId::generate(),
        heightCm: null,
        weightKg: null,
        photoUrl: null,
        modalities: [Modality::WALKING],
        primaryModality: null,
    ));
})->throws(RuntimeException::class, 'Usuário autenticado não encontrado.');

it('rejects onboarding data violating domain rules', function (array $overrides, string $exception) {
    $user = completeOnboardingTestUser();

    if (true === ($overrides['preCompleted'] ?? false)) {
        $user->completeOnboarding(
            heightCm: null,
            weightKg: null,
            photoUrl: null,
            modalities: [Modality::WALKING],
            primaryModality: null,
        );
    }

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldNotReceive('save');
    $this->eventDispatcher->shouldNotReceive('dispatch');

    $heightCm = $overrides['heightCm'] ?? null;
    $weightKg = $overrides['weightKg'] ?? null;
    $modalities = $overrides['modalities'] ?? [Modality::WALKING];
    $primaryModality = $overrides['primaryModality'] ?? null;

    assert(null === $heightCm || is_int($heightCm));
    assert(null === $weightKg || is_float($weightKg));
    assert(is_array($modalities));
    assert(null === $primaryModality || $primaryModality instanceof Modality);

    $command = new CompleteOnboardingCommand(
        userId: $user->id(),
        heightCm: $heightCm,
        weightKg: $weightKg,
        photoUrl: null,
        modalities: array_values($modalities),
        primaryModality: $primaryModality,
    );

    expect(fn () => ($this->handler)($command))->toThrow($exception);
})->with([
    'height below the minimum' => [
        ['heightCm' => 49],
        InvalidHeightException::class,
    ],
    'weight below the minimum' => [
        ['weightKg' => 19.9],
        InvalidWeightException::class,
    ],
    'no modality selected' => [
        ['modalities' => []],
        OnboardingNoModalitySelectedException::class,
    ],
    'primary modality not selected' => [
        ['modalities' => [Modality::WALKING], 'primaryModality' => Modality::RUNNING],
        OnboardingInvalidPrimaryModalityException::class,
    ],
    'onboarding already completed' => [
        ['preCompleted' => true],
        OnboardingAlreadyCompletedException::class,
    ],
]);
