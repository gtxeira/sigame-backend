<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\UserId;
use App\Identity\Infrastructure\Storage\S3ProfilePhotoStorage;
use App\Shared\Application\Storage\FileStorageInterface;

it('stores the photo under a user-scoped random key', function () {
    $path = tempnam(sys_get_temp_dir(), 'sigame-photo-');
    assert(is_string($path));
    file_put_contents($path, 'binary-contents');

    $userId = UserId::generate();
    $fileStorage = Mockery::mock(FileStorageInterface::class);
    $fileStorage
        ->shouldReceive('put')
        ->once()
        ->with(
            Mockery::on(static fn (string $key): bool => 1 === preg_match(
                '#^users/'.preg_quote($userId->value(), '#').'/[0-9a-f-]{36}\.png$#',
                $key,
            )),
            'binary-contents',
            'image/png',
        );

    $storage = new S3ProfilePhotoStorage($fileStorage);

    $key = $storage->store($userId, $path, 'image/png');

    expect($key)->toStartWith('users/'.$userId->value().'/')
        ->and($key)->toEndWith('.png');

    unlink($path);
});

it('maps the supported mime types to extensions', function (string $mimeType, string $extension) {
    $path = tempnam(sys_get_temp_dir(), 'sigame-photo-');
    assert(is_string($path));
    file_put_contents($path, 'binary-contents');

    $fileStorage = Mockery::mock(FileStorageInterface::class);
    $fileStorage->shouldReceive('put')->once();

    $key = (new S3ProfilePhotoStorage($fileStorage))->store(UserId::generate(), $path, $mimeType);

    expect($key)->toEndWith('.'.$extension);

    unlink($path);
})->with([
    'jpeg' => ['image/jpeg', 'jpg'],
    'png' => ['image/png', 'png'],
    'webp' => ['image/webp', 'webp'],
]);

it('rejects an unsupported mime type', function () {
    $path = tempnam(sys_get_temp_dir(), 'sigame-photo-');
    assert(is_string($path));
    file_put_contents($path, 'binary-contents');

    $fileStorage = Mockery::mock(FileStorageInterface::class);
    $fileStorage->shouldNotReceive('put');

    expect(fn () => (new S3ProfilePhotoStorage($fileStorage))->store(
        UserId::generate(),
        $path,
        'image/gif',
    ))->toThrow(RuntimeException::class);

    unlink($path);
});

it('only deletes an existing key', function () {
    $deleted = [];
    $fileStorage = Mockery::mock(FileStorageInterface::class);
    $fileStorage
        ->shouldReceive('delete')
        ->once()
        ->with('users/1/photo.png')
        ->andReturnUsing(static function (string $key) use (&$deleted): void {
            $deleted[] = $key;
        });

    $storage = new S3ProfilePhotoStorage($fileStorage);
    $storage->delete(null);

    expect($deleted)->toBe([]);

    $storage->delete('users/1/photo.png');

    expect($deleted)->toBe(['users/1/photo.png']);
});

it('resolves the public url only when a key exists', function () {
    $fileStorage = Mockery::mock(FileStorageInterface::class);
    $fileStorage->shouldReceive('url')->once()->with('users/1/photo.png')->andReturn('http://cdn.test/users/1/photo.png');

    $storage = new S3ProfilePhotoStorage($fileStorage);

    expect($storage->url(null))->toBeNull()
        ->and($storage->url('users/1/photo.png'))->toBe('http://cdn.test/users/1/photo.png');
});
