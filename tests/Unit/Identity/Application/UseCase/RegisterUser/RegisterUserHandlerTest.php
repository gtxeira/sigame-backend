<?php

declare(strict_types=1);

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\UseCase\RegisterUser\RegisterUserCommand;
use App\Identity\Application\UseCase\RegisterUser\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Event\UserRegistered;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;

beforeEach(function () {
    $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
    $this->passwordHasher = Mockery::mock(PasswordHasherInterface::class);
    $this->eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $this->handler = new RegisterUserHandler(
        userRepository: $this->userRepository,
        passwordHasher: $this->passwordHasher,
        eventDispatcher: $this->eventDispatcher,
    );
});

it('registers a user successfully', function () {
    $cpf = '52998224725';
    $email = 'test@example.com';
    $name = 'João Silva';
    $password = 'secret123';
    $hashedPassword = HashedPassword::fromHash('hashed_secret123');

    $this->userRepository
        ->shouldReceive('findByCpf')
        ->once()
        ->with($cpf)
        ->andReturn(null);

    $this->userRepository
        ->shouldReceive('findByEmail')
        ->once()
        ->andReturn(null);

    $this->passwordHasher
        ->shouldReceive('hash')
        ->once()
        ->with($password)
        ->andReturn($hashedPassword);

    $this->userRepository
        ->shouldReceive('save')
        ->once()
        ->with(Mockery::on(static fn (User $user) => $user->cpf()->value() === $cpf));

    $this->eventDispatcher
        ->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::type(UserRegistered::class));

    $command = new RegisterUserCommand(
        cpf: $cpf,
        email: $email,
        name: $name,
        password: $password,
    );

    $result = ($this->handler)($command);

    expect($result->isSuccess())->toBeTrue();

    $user = $result->value();

    expect($user->cpf()->value())->toBe($cpf)
        ->and($user->email()->value())->toBe($email)
        ->and($user->name())->toBe($name);
});

it('returns failure with expected validation errors', function (string $cpf, string $email, array $expectedCodes) {
    $command = new RegisterUserCommand(
        cpf: $cpf,
        email: $email,
        name: 'João Silva',
        password: 'secret123',
    );

    $result = ($this->handler)($command);

    expect($result->isFailure())->toBeTrue();

    $errors = $result->errors();

    expect($errors)->toHaveCount(count($expectedCodes))
        ->and(array_map(static fn ($error) => $error->code, $errors))->toBe($expectedCodes);
})->with([
    'invalid cpf' => ['invalid', 'test@example.com', ['CPF_INVALID']],
    'invalid email' => ['52998224725', 'invalid-email', ['EMAIL_INVALID']],
    'invalid cpf and email' => ['invalid', 'invalid-email', ['CPF_INVALID', 'EMAIL_INVALID']],
]);

it('returns failure when cpf already exists', function () {
    $cpf = '52998224725';
    $email = 'test@example.com';

    $existingUser = User::register(
        cpf: Cpf::fromString($cpf)->value(),
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Existing User',
    );

    $this->userRepository
        ->shouldReceive('findByCpf')
        ->andReturn($existingUser);

    $command = new RegisterUserCommand(
        cpf: $cpf,
        email: $email,
        name: 'João Silva',
        password: 'secret123',
    );

    $result = ($this->handler)($command);

    expect($result->isFailure())->toBeTrue();

    $error = $result->errors()[0];

    expect($error->code)->toBe('USER_CPF_ALREADY_EXISTS');
});

it('returns failure when email already exists', function () {
    $cpf = '52998224725';
    $email = 'test@example.com';

    $existingUser = User::register(
        cpf: Cpf::fromString('47705122014')->value(),
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Existing User',
    );

    $this->userRepository
        ->shouldReceive('findByCpf')
        ->andReturn(null);

    $this->userRepository
        ->shouldReceive('findByEmail')
        ->andReturn($existingUser);

    $command = new RegisterUserCommand(
        cpf: $cpf,
        email: $email,
        name: 'João Silva',
        password: 'secret123',
    );

    $result = ($this->handler)($command);

    expect($result->isFailure())->toBeTrue();

    $error = $result->errors()[0];

    expect($error->code)->toBe('EMAIL_ALREADY_EXISTS');
});

it('does not save user on validation failure', function () {
    $command = new RegisterUserCommand(
        cpf: 'invalid',
        email: 'test@example.com',
        name: 'João Silva',
        password: 'secret123',
    );

    $this->userRepository->shouldNotReceive('save');
    $this->eventDispatcher->shouldNotReceive('dispatch');

    ($this->handler)($command);
});

it('does not save user on business rule violation', function () {
    $cpf = '52998224725';
    $email = 'test@example.com';

    $existingUser = User::register(
        cpf: Cpf::fromString($cpf)->value(),
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Existing User',
    );

    $this->userRepository
        ->shouldReceive('findByCpf')
        ->andReturn($existingUser);

    $this->userRepository->shouldNotReceive('save');
    $this->eventDispatcher->shouldNotReceive('dispatch');

    $command = new RegisterUserCommand(
        cpf: $cpf,
        email: $email,
        name: 'João Silva',
        password: 'secret123',
    );

    ($this->handler)($command);
});
