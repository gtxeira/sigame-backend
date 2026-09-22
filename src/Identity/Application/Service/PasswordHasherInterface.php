<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Domain\ValueObject\HashedPassword;

/**
 * Interface para hash de senhas (porta no Domain/Application).
 */
interface PasswordHasherInterface
{
    /**
     * Gera o hash de uma senha em texto puro.
     */
    public function hash(string $plainPassword): HashedPassword;

    /**
     * Verifica se uma senha em texto puro corresponde ao hash.
     */
    public function verify(string $plainPassword, HashedPassword $hashedPassword): bool;
}
