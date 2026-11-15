<?php

declare(strict_types=1);

use App\Identity\Application\Service\AccountExistsNoticeThrottleInterface;
use App\Identity\Application\Service\IdentityMailDispatcherInterface;
use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\UseCase\RegisterUser\RegisterUserCommand;
use App\Identity\Application\UseCase\RegisterUser\RegisterUserHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserRegistered;
use App\Identity\Domain\User\Exception\EmailAlreadyExistsException;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Email;

function registerUserTestHandler(
    UserRepositoryInterface $users,
    PasswordHasherInterface $hasher,
    IdentityMailDispatcherInterface $dispatcher,
    AccountExistsNoticeThrottleInterface $throttle,
    EventDispatcherInterface $events,
): RegisterUserHandler {
    return new RegisterUserHandler(
        userRepository: $users,
        passwordHasher: $hasher,
        mailDispatcher: $dispatcher,
        accountExistsNoticeThrottle: $throttle,
        eventDispatcher: $events,
    );
}

function registerUserTestExistingUser(string $email = 'existing@example.com'): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Existing User',
    );
}

it('registers a new user without cpf and dispatches the registered event', function () {
    $hashedPassword = HashedPassword::fromHash('hashed_secret123');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn(null);
    $users->shouldReceive('save')->once()->with(Mockery::on(
        static fn (User $user): bool => null === $user->cpf()
            && 'test@example.com' === $user->email()->value()
            && 'João Silva' === $user->name(),
    ));

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->once()->with('secret123')->andReturn($hashedPassword);

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchAccountExists');

    $throttle = Mockery::mock(AccountExistsNoticeThrottleInterface::class);
    $throttle->shouldNotReceive('reserve');

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::type(UserRegistered::class));

    $result = registerUserTestHandler($users, $hasher, $dispatcher, $throttle, $events)(
        new RegisterUserCommand(
            email: 'test@example.com',
            name: 'João Silva',
            password: 'secret123',
        ),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBeTrue();
});

it('hashes the password and notifies the owner when the email already exists', function () {
    $existing = registerUserTestExistingUser();

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')
        ->once()
        ->with(Mockery::on(static fn (Email $email): bool => 'existing@example.com' === $email->value()))
        ->andReturn($existing);
    $users->shouldNotReceive('save');

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->once()->with('secret123')->andReturn(HashedPassword::fromHash('hashed'));

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatchAccountExists')
        ->once()
        ->with(Mockery::on(static fn (Email $email): bool => 'existing@example.com' === $email->value()));

    $throttle = Mockery::mock(AccountExistsNoticeThrottleInterface::class);
    $throttle->shouldReceive('reserve')->once()->andReturn(true);

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldNotReceive('dispatch');

    $result = registerUserTestHandler($users, $hasher, $dispatcher, $throttle, $events)(
        new RegisterUserCommand(
            email: 'existing@example.com',
            name: 'João Silva',
            password: 'secret123',
        ),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBeFalse();
});

it('skips the notice while its cooldown is active', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn(registerUserTestExistingUser());

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->once()->andReturn(HashedPassword::fromHash('hashed'));

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatchAccountExists');

    $throttle = Mockery::mock(AccountExistsNoticeThrottleInterface::class);
    $throttle->shouldReceive('reserve')->once()->andReturn(false);

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldNotReceive('dispatch');

    $result = registerUserTestHandler(
        $users,
        $hasher,
        $dispatcher,
        $throttle,
        $events,
    )(new RegisterUserCommand(
        email: 'existing@example.com',
        name: 'João Silva',
        password: 'secret123',
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBeFalse();
});

it('treats a uniqueness race on save as an existing account', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn(null);
    $users->shouldReceive('save')->once()->andThrow(EmailAlreadyExistsException::create());

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->once()->andReturn(HashedPassword::fromHash('hashed'));

    $dispatcher = Mockery::mock(IdentityMailDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatchAccountExists')->once();

    $throttle = Mockery::mock(AccountExistsNoticeThrottleInterface::class);
    $throttle->shouldReceive('reserve')->once()->andReturn(true);

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldNotReceive('dispatch');

    $result = registerUserTestHandler($users, $hasher, $dispatcher, $throttle, $events)(
        new RegisterUserCommand(
            email: 'race@example.com',
            name: 'João Silva',
            password: 'secret123',
        ),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBeFalse();
});

it('returns validation errors without hashing or touching the repository', function () {
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('findByEmail');
    $users->shouldNotReceive('save');

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldNotReceive('hash');

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldNotReceive('dispatch');

    $result = registerUserTestHandler(
        $users,
        $hasher,
        Mockery::mock(IdentityMailDispatcherInterface::class),
        Mockery::mock(AccountExistsNoticeThrottleInterface::class),
        $events,
    )(new RegisterUserCommand(
        email: 'invalid-email',
        name: 'João Silva',
        password: 'short',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and(array_map(static fn ($error) => $error->code, $result->errors()))
        ->toBe(['INVALID_FORMAT', 'STRING_TOO_SHORT']);
});
