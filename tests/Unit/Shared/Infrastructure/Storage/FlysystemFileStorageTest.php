<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Storage\FlysystemFileStorage;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * @return array{0: FlysystemFileStorage, 1: FilesystemOperator, 2: string}
 */
function flysystemStorageFixture(): array
{
    $root = sys_get_temp_dir().'/sigame-flysystem-'.bin2hex(random_bytes(6));
    $filesystem = new Filesystem(new LocalFilesystemAdapter($root));

    return [new FlysystemFileStorage($filesystem, 'http://cdn.test'), $filesystem, $root];
}

it('writes the contents to the storage', function () {
    [$storage, $filesystem, $root] = flysystemStorageFixture();

    $storage->put('users/1/photo.png', 'binary-contents', 'image/png');

    expect($filesystem->fileExists('users/1/photo.png'))->toBeTrue()
        ->and($filesystem->read('users/1/photo.png'))->toBe('binary-contents');

    $filesystem->delete('users/1/photo.png');
    @rmdir($root.'/users/1');
    @rmdir($root.'/users');
    @rmdir($root);
});

it('builds the public url without duplicating slashes', function () {
    $storage = new FlysystemFileStorage(Mockery::mock(FilesystemOperator::class), 'http://cdn.test/');

    expect($storage->url('users/1/photo.png'))->toBe('http://cdn.test/users/1/photo.png');
});

it('deletes an existing object', function () {
    [$storage, $filesystem, $root] = flysystemStorageFixture();
    $storage->put('users/1/photo.png', 'binary-contents', 'image/png');

    $storage->delete('users/1/photo.png');

    expect($filesystem->fileExists('users/1/photo.png'))->toBeFalse();

    @rmdir($root.'/users/1');
    @rmdir($root.'/users');
    @rmdir($root);
});

it('ignores the deletion of a missing object', function () {
    [$storage, $filesystem, $root] = flysystemStorageFixture();

    $storage->delete('users/1/photo.png');

    expect($filesystem->fileExists('users/1/photo.png'))->toBeFalse();

    @rmdir($root);
});
