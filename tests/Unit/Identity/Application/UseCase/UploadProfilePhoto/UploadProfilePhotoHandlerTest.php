<?php

declare(strict_types=1);

use App\Identity\Application\Storage\ProfilePhotoStorageInterface;
use App\Identity\Application\UseCase\UploadProfilePhoto\UploadProfilePhotoCommand;
use App\Identity\Application\UseCase\UploadProfilePhoto\UploadProfilePhotoHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Email;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

function uploadPhotoTestUser(): User
{
    return User::register(
        cpf: null,
        email: Email::fromString('upload@example.com')->value(),
        password: HashedPassword::fromHash('hashed_password_123'),
        name: 'João Silva',
    );
}

function uploadPhotoTestCommand(User $user): UploadProfilePhotoCommand
{
    return new UploadProfilePhotoCommand(
        userId: $user->id(),
        sourcePath: '/tmp/photo.png',
        mimeType: 'image/png',
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

    $this->handler = new UploadProfilePhotoHandler(
        userRepository: $this->userRepository,
        photoStorage: $this->photoStorage,
        lockFactory: $lockFactory,
        logger: $this->logger,
    );
});

it('stores a first photo and persists the key', function () {
    $user = uploadPhotoTestUser();

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->photoStorage
        ->shouldReceive('store')
        ->once()
        ->with(
            Mockery::on(static fn (UserId $id): bool => $id->equals($user->id())),
            '/tmp/photo.png',
            'image/png',
        )
        ->andReturn('users/photo-new.png');
    $this->photoStorage->shouldNotReceive('delete');
    $this->userRepository->shouldReceive('save')->once()->with($user);

    $result = ($this->handler)(uploadPhotoTestCommand($user));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->photoKey())->toBe('users/photo-new.png');
});

it('replaces the previous photo and removes the old object', function () {
    $user = uploadPhotoTestUser();
    $user->changePhoto('users/photo-old.png');

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->photoStorage->shouldReceive('store')->once()->andReturn('users/photo-new.png');
    $this->userRepository->shouldReceive('save')->once();
    $this->photoStorage->shouldReceive('delete')->once()->with('users/photo-old.png');

    $result = ($this->handler)(uploadPhotoTestCommand($user));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->photoKey())->toBe('users/photo-new.png');
});

it('removes the new object when persistence fails', function () {
    $user = uploadPhotoTestUser();
    $user->changePhoto('users/photo-old.png');

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->photoStorage->shouldReceive('store')->once()->andReturn('users/photo-new.png');
    $this->userRepository->shouldReceive('save')->once()->andThrow(new RuntimeException('database down'));
    $this->photoStorage->shouldReceive('delete')->once()->with('users/photo-new.png');
    $this->photoStorage->shouldNotReceive('delete')->with('users/photo-old.png');

    expect(fn () => ($this->handler)(uploadPhotoTestCommand($user)))
        ->toThrow(RuntimeException::class, 'database down');
});

it('logs and keeps the upload when removing the old object fails', function () {
    $user = uploadPhotoTestUser();
    $user->changePhoto('users/photo-old.png');

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->photoStorage->shouldReceive('store')->once()->andReturn('users/photo-new.png');
    $this->userRepository->shouldReceive('save')->once();
    $this->photoStorage
        ->shouldReceive('delete')
        ->once()
        ->with('users/photo-old.png')
        ->andThrow(new RuntimeException('storage unavailable'));
    $this->logger->shouldReceive('warning')->once();

    $result = ($this->handler)(uploadPhotoTestCommand($user));

    expect($result->isSuccess())->toBeTrue()
        ->and($user->photoKey())->toBe('users/photo-new.png');
});

it('fails when the lock cannot be acquired', function () {
    $user = uploadPhotoTestUser();
    $this->lockAcquired = false;

    $this->userRepository->shouldReceive('findById')->once()->andReturn($user);
    $this->photoStorage->shouldNotReceive('store');
    $this->userRepository->shouldNotReceive('save');

    ($this->handler)(uploadPhotoTestCommand($user));
})->throws(RuntimeException::class);

it('throws when the authenticated user does not exist', function () {
    $this->userRepository->shouldReceive('findById')->once()->andReturn(null);
    $this->photoStorage->shouldNotReceive('store');

    ($this->handler)(new UploadProfilePhotoCommand(
        userId: UserId::generate(),
        sourcePath: '/tmp/photo.png',
        mimeType: 'image/png',
    ));
})->throws(RuntimeException::class, 'Usuário autenticado não encontrado.');
