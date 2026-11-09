<?php

declare(strict_types=1);

namespace App\Shared\Application\User;

/**
 * Porta de leitura síncrona do nível de um usuário.
 *
 * Módulos que dependem do nível (por exemplo, Groups) usam esta porta em vez de
 * importar classes de Identity. O adapter concreto vive no módulo dono do dado.
 */
interface UserLevelReaderInterface
{
    /**
     * Retorna o nível atual ou `null` quando o usuário não existe.
     */
    public function levelOf(string $userId): ?int;
}
