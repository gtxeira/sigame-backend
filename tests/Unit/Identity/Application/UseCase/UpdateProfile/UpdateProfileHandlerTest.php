<?php

declare(strict_types=1);

use App\Identity\Application\UseCase\UpdateProfile\UpdateProfileCommand;
use App\Identity\Application\UseCase\UpdateProfile\UpdateProfileHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\Height;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Identity\Domain\User\ValueObject\Weight;
use App\Shared\Domain\ValueObject\Email;

function updateProfileTestUser(): User
{
    $user = User::register(
        cpf: null,
        email: Email::fromString('profile@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'João Silva',
    );

    $user->changeProfile(
        $user->profile()
            ->withHeight(Height::fromCentimeters(165)->value())
            ->withWeight(Weight::fromKilograms(62.5)->value()),
    );
    $user->changePhoto('users/photo.png');

    return $user;
}

function updateProfileTestCommand(
    User $user,
    bool $nameProvided = false,
    ?string $name = null,
    bool $heightProvided = false,
    ?int $heightCm = null,
    bool $weightProvided = false,
    ?float $weightKg = null,
): UpdateProfileCommand {
    return new UpdateProfileCommand(
        userId: $user->id(),
        nameProvided: $nameProvided,
        name: $name,
        heightProvided: $heightProvided,
        heightCm: $heightCm,
        weightProvided: $weightProvided,
        weightKg: $weightKg,
    );
}

beforeEach(function () {
    $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
    $this->handler = new UpdateProfileHandler($this->userRepository);
});

it('changes only the name and keeps the other fields', function () {
    $user = updateProfileTestUser();

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldReceive('save')->once()->with($user);

    $result = ($this->handler)(updateProfileTestCommand(
        $user,
        nameProvided: true,
        name: '  Maria Silva  ',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->name())->toBe('Maria Silva')
        ->and($user->height()?->centimeters())->toBe(165)
        ->and($user->weight()?->kilograms())->toBe(62.5)
        ->and($user->photoKey())->toBe('users/photo.png');
});

it('changes only the height when the field is present', function () {
    $user = updateProfileTestUser();

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldReceive('save')->once();

    $result = ($this->handler)(updateProfileTestCommand(
        $user,
        heightProvided: true,
        heightCm: 178,
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->name())->toBe('João Silva')
        ->and($user->height()?->centimeters())->toBe(178)
        ->and($user->weight()?->kilograms())->toBe(62.5);
});

it('changes only the weight when the field is present', function () {
    $user = updateProfileTestUser();

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldReceive('save')->once();

    $result = ($this->handler)(updateProfileTestCommand(
        $user,
        weightProvided: true,
        weightKg: 70.2,
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->weight()?->kilograms())->toBe(70.2)
        ->and($user->height()?->centimeters())->toBe(165);
});

it('clears the height when explicitly null', function () {
    $user = updateProfileTestUser();

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldReceive('save')->once();

    $result = ($this->handler)(updateProfileTestCommand($user, heightProvided: true));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->height())->toBeNull()
        ->and($user->weight()?->kilograms())->toBe(62.5);
});

it('clears the weight when explicitly null', function () {
    $user = updateProfileTestUser();

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldReceive('save')->once();

    $result = ($this->handler)(updateProfileTestCommand($user, weightProvided: true));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->weight())->toBeNull()
        ->and($user->height()?->centimeters())->toBe(165);
});

it('fails when no field is provided', function () {
    $user = updateProfileTestUser();

    $this->userRepository->shouldNotReceive('findById');

    ($this->handler)(updateProfileTestCommand($user));
})->throws(LogicException::class);

it('fails with the business code when the height is out of range', function () {
    $user = updateProfileTestUser();

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldNotReceive('save');

    $result = ($this->handler)(updateProfileTestCommand($user, heightProvided: true, heightCm: 49));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('USER_HEIGHT_INVALID')
        ->and($user->height()?->centimeters())->toBe(165);
});

it('fails with the business code when the weight is out of range', function () {
    $user = updateProfileTestUser();

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldNotReceive('save');

    $result = ($this->handler)(updateProfileTestCommand($user, weightProvided: true, weightKg: 19.9));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('USER_WEIGHT_INVALID')
        ->and($user->weight()?->kilograms())->toBe(62.5);
});

it('does not apply any change when one of the fields is invalid', function () {
    $user = updateProfileTestUser();

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldNotReceive('save');

    $result = ($this->handler)(updateProfileTestCommand(
        $user,
        nameProvided: true,
        name: 'Maria Silva',
        heightProvided: true,
        heightCm: 49,
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($user->name())->toBe('João Silva')
        ->and($user->height()?->centimeters())->toBe(165);
});

it('throws when the authenticated user does not exist', function () {
    $this->userRepository->shouldReceive('findById')->once()->andReturn(null);

    ($this->handler)(new UpdateProfileCommand(
        userId: UserId::generate(),
        nameProvided: true,
        name: 'Maria Silva',
        heightProvided: false,
        heightCm: null,
        weightProvided: false,
        weightKg: null,
    ));
})->throws(RuntimeException::class, 'Usuário autenticado não encontrado.');
