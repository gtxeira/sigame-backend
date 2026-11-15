<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Shared\Domain\ValueObject\Email;

/**
 * Porta do aviso enviado quando alguém tenta cadastrar um e-mail já existente.
 *
 * O aviso vai apenas para o dono da conta; o cooldown por e-mail evita que o
 * formulário público seja usado para bombardear a caixa de entrada.
 */
interface AccountExistsNoticeThrottleInterface
{
    /**
     * @return bool true quando o aviso deve ser enfileirado
     */
    public function reserve(Email $email): bool;
}
