<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use App\Identity\Application\DTO\IssuedPasswordResetTicket;
use OpenApi\Attributes as OA;

/**
 * Resposta da validação do código de reset.
 */
#[OA\Schema(description: 'Ticket temporário que autoriza a redefinição de senha.')]
final readonly class PasswordResetTicketResponse
{
    public function __construct(
        #[OA\Property(description: 'Ticket de uso único, válido por poucos minutos.', example: 'a1b2c3...')]
        public string $resetToken,
        #[OA\Property(description: 'Validade do ticket em segundos.', example: 300)]
        public int $expiresIn,
    ) {
    }

    public static function fromIssued(IssuedPasswordResetTicket $ticket): self
    {
        return new self(
            resetToken: $ticket->rawToken,
            expiresIn: $ticket->expiresIn,
        );
    }
}
