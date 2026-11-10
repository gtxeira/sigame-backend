<?php

declare(strict_types=1);

namespace App\Shared\Application\User;

/**
 * Contato mínimo de um usuário para composição de notificações.
 *
 * Não expõe CPF, telefone, peso, altura ou credenciais.
 */
final readonly class UserContact
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public bool $active,
    ) {
    }
}
