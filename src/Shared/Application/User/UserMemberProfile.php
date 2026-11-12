<?php

declare(strict_types=1);

namespace App\Shared\Application\User;

/**
 * Dados públicos de um participante para listagens de grupo.
 *
 * Não expõe CPF, e-mail, peso, altura, nascimento nem credenciais (RNF01).
 */
final readonly class UserMemberProfile
{
    public function __construct(
        public string $id,
        public string $name,
        public int $level,
        public ?string $photoUrl,
    ) {
    }
}
