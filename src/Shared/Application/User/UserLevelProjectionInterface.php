<?php

declare(strict_types=1);

namespace App\Shared\Application\User;

/**
 * Porta de escrita síncrona da projeção de nível em `users.level`.
 *
 * A fonte de verdade é `user_progress.level`; Identity mantém a projeção usada
 * por Groups e pela segurança. O adapter concreto vive no módulo dono do dado.
 */
interface UserLevelProjectionInterface
{
    /**
     * Projeta o nível informado.
     *
     * Retorna `false` quando o usuário não existe ou o nível é inválido.
     */
    public function project(string $userId, int $level): bool;
}
