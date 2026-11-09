<?php

declare(strict_types=1);

namespace App\Shared\Application\Storage;

/**
 * Porta para armazenamento de arquivos em object storage.
 *
 * A chave (`key`) é o identificador estável do objeto; quem consome a API
 * recebe a URL pública derivada dela.
 */
interface FileStorageInterface
{
    public function put(string $key, string $contents, string $contentType): void;

    public function delete(string $key): void;

    public function url(string $key): string;
}
