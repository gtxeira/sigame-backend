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
    $this->userRepository = $this->createMock(UserRepositoryInterface::class);
    $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

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
        ->method('findByCpf')
        ->willReturn(null);

    $this->userRepository
        ->method('findByEmail')
        ->willReturn(null);

    $this->passwordHasher
        ->method('hash')
        ->with($password)
        ->willReturn($hashedPassword);

    $this->userRepository
        ->expects($this->once())
        ->method('save')
        ->with($this->callback(fn (User $user) => $user->cpf()->value() === $cpf));

    $this->eventDispatcher
        ->expects($this->once())
        ->method('dispatch')
        ->with($this->callback(fn (UserRegistered $event) => $event instanceof UserRegistered));

    $command = new RegisterUserCommand(
        cpf: $cpf,
        email: $email,
        name: $name,
        password: $password,
    );

    $result = ($this->handler)($command);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBeInstanceOf(User::class)
        ->and($result->value()->cpf()->value())->toBe($cpf)
        ->and($result->value()->email()->value())->toBe($email)
        ->and($result->value()->name())->toBe($name);
});

it('returns failure for invalid cpf', function () {
    $command = new RegisterUserCommand(
        cpf: 'invalid',
        email: 'test@example.com',
        name: 'João Silva',
        password: 'secret123',
    );

    $result = ($this->handler)($command);

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors())->toHaveCount(1)
        ->and($result->errors()[0]->code)->toBe('CPF_INVALID');
});

it('returns failure for invalid email', function () {
    $command = new RegisterUserCommand(
        cpf: '52998224725',
        email: 'invalid-email',
        name: 'João Silva',
        password: 'secret123',
    );

    $result = ($this->handler)($command);

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors())->toHaveCount(1)
        ->and($result->errors()[0]->code)->toBe('EMAIL_INVALID');
});

it('accumulates multiple validation errors', function () {
    $command = new RegisterUserCommand(
        cpf: 'invalid',
        email: 'invalid-email',
        name: 'João Silva',
        password: 'secret123',
    );

    $result = ($this->handler)($command);

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors())->toHaveCount(2);
});

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
        ->method('findByCpf')
        ->willReturn($existingUser);

    $command = new RegisterUserCommand(
        cpf: $cpf,
        email: $email,
        name: 'João Silva',
        password: 'secret123',
    );

    $result = ($this->handler)($command);

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('USER_CPF_ALREADY_EXISTS');
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
        ->method('findByCpf')
        ->willReturn(null);

    $this->userRepository
        ->method('findByEmail')
        ->willReturn($existingUser);

    $command = new RegisterUserCommand(
        cpf: $cpf,
        email: $email,
        name: 'João Silva',
        password: 'secret123',
    );

    $result = ($this->handler)($command);

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('EMAIL_ALREADY_EXISTS');
});

it('does not save user on validation failure', function () {
    $command = new RegisterUserCommand(
        cpf: 'invalid',
        email: 'test@example.com',
        name: 'João Silva',
        password: 'secret123',
    );

    $this->userRepository
        ->expects($this->never())
        ->method('save');

    $this->eventDispatcher
        ->expects($this->never())
        ->method('dispatch');

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
        ->method('findByCpf')
        ->willReturn($existingUser);

    $this->userRepository
        ->expects($this->never())
        ->method('save');

    $this->eventDispatcher
        ->expects($this->never())
        ->method('dispatch');

    $command = new RegisterUserCommand(
        cpf: $cpf,
        email: $email,
        name: 'João Silva',
        password: 'secret123',
    );

    ($this->handler)($command);
});
