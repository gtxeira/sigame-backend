<?php

declare(strict_types=1);

namespace App\Shared\Application\User;

/**
 * Porta de leitura síncrona do estado de acesso de um usuário.
 *
 * Módulos que dependem de a conta estar ativa (por exemplo, aprovação de
 * participação em grupo e notificações) usam esta porta em vez de importar
 * classes de Identity. O adapter concreto vive no módulo dono do dado.
 */
interface UserActiveReaderInterface
{
    /**
     * Retorna `false` quando o usuário não existe ou está suspenso.
     */
    public function isActive(string $userId): bool;
}
