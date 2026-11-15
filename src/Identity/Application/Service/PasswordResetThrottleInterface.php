<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Shared\Domain\ValueObject\Email;

/**
 * Porta do cooldown de pedidos de redefinição de senha.
 *
 * O estado é por e-mail, exista conta ou não, para que a resposta seja a mesma
 * em ambos os casos e não revele quais endereços estão cadastrados.
 */
interface PasswordResetThrottleInterface
{
    /**
     * Reserva a janela de cooldown do e-mail.
     *
     * @return int|null segundos restantes quando já existe reserva ativa;
     *                  null quando esta chamada reservou a janela
     */
    public function reserve(Email $email): ?int;
}
