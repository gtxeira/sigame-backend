<?php

declare(strict_types=1);

namespace App\Identity\Application\Storage;

use App\Identity\Domain\User\ValueObject\UserId;

/**
 * Porta de armazenamento das fotos de perfil.
 *
 * Expõe apenas o necessário para o caso de uso: gravar a partir de um arquivo
 * temporário, remover e resolver a URL pública. A chave do objeto é opaca e
 * não depende da URL do provedor.
 */
interface ProfilePhotoStorageInterface
{
    public function store(UserId $userId, string $sourcePath, string $mimeType): string;

    public function delete(?string $key): void;

    public function url(?string $key): ?string;
}
