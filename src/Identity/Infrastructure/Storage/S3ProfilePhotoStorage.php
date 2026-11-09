<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Storage;

use App\Identity\Application\Storage\ProfilePhotoStorageInterface;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Application\Storage\FileStorageInterface;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * Implementação da porta de fotos usando o storage compartilhado.
 *
 * A chave tem o usuário como prefixo e um UUID aleatório como nome, o que
 * evita colisão e torna a URL pública não adivinhável.
 */
final readonly class S3ProfilePhotoStorage implements ProfilePhotoStorageInterface
{
    private const array EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private FileStorageInterface $fileStorage,
    ) {
    }

    public function store(UserId $userId, string $sourcePath, string $mimeType): string
    {
        $extension = self::EXTENSIONS[$mimeType] ?? null;

        if (null === $extension) {
            throw new RuntimeException(sprintf('Tipo de imagem não suportado: %s.', $mimeType));
        }

        $contents = file_get_contents($sourcePath);

        if (false === $contents) {
            throw new RuntimeException('Não foi possível ler o arquivo da foto de perfil.');
        }

        $key = sprintf(
            'users/%s/%s.%s',
            $userId->value(),
            Uuid::v4()->toRfc4122(),
            $extension,
        );

        $this->fileStorage->put($key, $contents, $mimeType);

        return $key;
    }

    public function delete(?string $key): void
    {
        if (null === $key) {
            return;
        }

        $this->fileStorage->delete($key);
    }

    public function url(?string $key): ?string
    {
        if (null === $key) {
            return null;
        }

        return $this->fileStorage->url($key);
    }
}
