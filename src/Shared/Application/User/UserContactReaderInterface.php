<?php

declare(strict_types=1);

namespace App\Shared\Application\User;

/**
 * Porta de leitura síncrona do contato de um usuário.
 *
 * O adapter concreto vive no módulo Identity. Módulos consumidores importam
 * apenas esta interface e o DTO do kernel.
 */
interface UserContactReaderInterface
{
    /**
     * Retorna `null` quando o usuário não existe ou o identificador é inválido.
     */
    public function contact(string $userId): ?UserContact;
}
