<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Storage;

use App\Shared\Application\Storage\FileStorageInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adapter de storage sobre Flysystem.
 *
 * A escrita usa visibilidade privada: o acesso de leitura é concedido apenas
 * pelo endpoint público do bucket (website), sem credencial de escrita.
 */
final readonly class FlysystemFileStorage implements FileStorageInterface
{
    public function __construct(
        #[Autowire(service: 'storage.filesystem')]
        private FilesystemOperator $filesystem,
        #[Autowire('%env(S3_PUBLIC_URL)%')]
        private string $publicUrl,
    ) {
    }

    public function put(string $key, string $contents, string $contentType): void
    {
        $this->filesystem->write($key, $contents, [
            'ContentType' => $contentType,
            'visibility' => 'private',
        ]);
    }

    public function delete(string $key): void
    {
        if (!$this->filesystem->fileExists($key)) {
            return;
        }

        $this->filesystem->delete($key);
    }

    public function url(string $key): string
    {
        return rtrim($this->publicUrl, '/').'/'.ltrim($key, '/');
    }
}
