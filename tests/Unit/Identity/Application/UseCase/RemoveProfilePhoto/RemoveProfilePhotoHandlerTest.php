<?php

declare(strict_types=1);

use App\Identity\Application\Storage\ProfilePhotoStorageInterface;
use App\Identity\Application\UseCase\RemoveProfilePhoto\RemoveProfilePhotoHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Email;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

function removePhotoTestUser(): User
{
    return User::register(
        cpf: null,
        email: Email::fromString('remove@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'João Silva',
    );
}

beforeEach(function () {
    $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
    $this->photoStorage = Mockery::mock(ProfilePhotoStorageInterface::class);
    $this->logger = Mockery::mock(LoggerInterface::class);
    $this->lock = Mockery::mock(SharedLockInterface::class);
    $this->lockAcquired = true;
    $this->lock->shouldReceive('acquire')->with(true)->andReturnUsing(fn (): bool => $this->lockAcquired);
    $this->lock->shouldReceive('release')->zeroOrMoreTimes();

    $lockFactory = Mockery::mock(LockFactory::class);
    $lockFactory->shouldReceive('createLock')->andReturn($this->lock);

    $this->handler = new RemoveProfilePhotoHandler(
        userRepository: $this->userRepository,
        photoStorage: $this->photoStorage,
        lockFactory: $lockFactory,
        logger: $this->logger,
    );
});

it('removes the photo and its object', function () {
    $user = removePhotoTestUser();
    $user->changePhoto('users/photo.png');

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldReceive('save')->once()->with($user);
    $this->photoStorage->shouldReceive('delete')->once()->with('users/photo.png');

    $result = ($this->handler)($user->id());

    expect($result->isSuccess())->toBeTrue()
        ->and($user->photoKey())->toBeNull();
});

it('is idempotent when there is no photo', function () {
    $user = removePhotoTestUser();

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldNotReceive('save');
    $this->photoStorage->shouldNotReceive('delete');

    $result = ($this->handler)($user->id());

    expect($result->isSuccess())->toBeTrue()
        ->and($user->photoKey())->toBeNull();
});

it('logs and keeps the removal when deleting the object fails', function () {
    $user = removePhotoTestUser();
    $user->changePhoto('users/photo.png');

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldReceive('save')->once();
    $this->photoStorage
        ->shouldReceive('delete')
        ->once()
        ->with('users/photo.png')
        ->andThrow(new RuntimeException('storage unavailable'));
    $this->logger->shouldReceive('warning')->once();

    $result = ($this->handler)($user->id());

    expect($result->isSuccess())->toBeTrue()
        ->and($user->photoKey())->toBeNull();
});

it('fails when the lock cannot be acquired', function () {
    $user = removePhotoTestUser();
    $this->lockAcquired = false;

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->userRepository->shouldNotReceive('save');
    $this->photoStorage->shouldNotReceive('delete');

    ($this->handler)($user->id());
})->throws(RuntimeException::class);

it('throws when the authenticated user does not exist', function () {
    $this->userRepository->shouldReceive('findById')->once()->andReturn(null);
    $this->photoStorage->shouldNotReceive('delete');

    ($this->handler)(UserId::generate());
})->throws(RuntimeException::class, 'Usuário autenticado não encontrado.');
