<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use App\Identity\Application\UseCase\Admin\AccountStatusChangeResult;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Resultado da suspensão/reativação de uma conta.')]
final readonly class AccountStatusChangeResponse
{
    public function __construct(
        #[OA\Property(description: 'Indica se o estado da conta mudou nesta operação.', example: true)]
        public bool $changed,

        #[OA\Property(ref: '#/components/schemas/AdminUserResponse')]
        public AdminUserResponse $user,

        #[OA\Property(description: 'Registro criado, quando houve mudança.', nullable: true)]
        public ?UserSuspensionResponse $history,
    ) {
    }

    public static function fromResult(AccountStatusChangeResult $result): self
    {
        return new self(
            changed: $result->changed,
            user: AdminUserResponse::fromEntity($result->user),
            history: null === $result->history ? null : UserSuspensionResponse::fromEntity($result->history),
        );
    }
}
