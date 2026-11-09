<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\UploadProfilePhoto;

use App\Identity\Domain\User\ValueObject\UserId;

/**
 * Upload (ou substituição) da foto do usuário autenticado.
 */
final readonly class UploadProfilePhotoCommand
{
    public function __construct(
        private UserId $userId,
        private string $sourcePath,
        private string $mimeType,
    ) {
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function sourcePath(): string
    {
        return $this->sourcePath;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }
}
