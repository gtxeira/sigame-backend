<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\ValueObject\UserLevel;
use App\Identity\Domain\ValueObject\UserStatus;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Dados do usuário cadastrado')]
final readonly class RegisterUserResponse
{
    public function __construct(
        #[OA\Property(description: 'Identificador único do usuário.', format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,

        #[OA\Property(description: 'CPF do usuário, formatado com pontuação.', example: '477.051.220-14')]
        public string $cpf,

        #[OA\Property(description: 'Endereço de e-mail do usuário.', format: 'email', example: 'user@example.com')]
        public string $email,

        #[OA\Property(description: 'Nome completo do usuário.', example: 'John Doe')]
        public string $name,

        #[OA\Property(description: 'Nível inicial atribuído ao usuário após o cadastro.', example: 1, const: UserLevel::MIN_LEVEL)]
        public int $level,

        #[OA\Property(description: 'Status inicial do usuário após o cadastro.', example: UserStatus::ACTIVE, enum: UserStatus::class, const: UserStatus::ACTIVE)]
        public string $status,

        #[OA\Property(description: 'Data e hora de criação do usuário no formato RFC 3339.', format: 'date-time', example: '2026-09-21T10:00:00.000+00:00')]
        public string $createdAt,
    ) {
    }

    /**
     * Factory method para converter a Entidade do Domínio no DTO de Resposta.
     */
    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->id()->value(),
            cpf: $user->cpf()->formatted(),
            email: $user->email()->value(),
            name: $user->name(),
            level: $user->level()->value(),
            status: $user->status()->value,
            createdAt: $user->createdAt()->toIsoStringWithMilliseconds(),
        );
    }
}
