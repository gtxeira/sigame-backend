<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Repository;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;

/**
 * Interface de repositório para a entidade User (porta no Domain).
 */
interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function findByCpf(Cpf $cpf): ?User;

    public function findByProviderSubject(
        IdentityProvider $provider,
        ProviderSubject $subject,
    ): ?User;
}
