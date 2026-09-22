<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Domain\ValueObject\HashedPassword;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Implementação Symfony do hash de senhas.
 *
 * Usa UserPasswordHasherInterface para obter o hasher correto.
 */
final readonly class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function hash(string $plainPassword): HashedPassword
    {
        $user = new InMemoryUser('dummy', null, ['ROLE_USER']);
        $hash = $this->passwordHasher->hashPassword($user, $plainPassword);

        return HashedPassword::fromHash($hash);
    }

    public function verify(string $plainPassword, HashedPassword $hashedPassword): bool
    {
        $user = new InMemoryUser('dummy', $hashedPassword->value(), ['ROLE_USER']);

        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }
}
