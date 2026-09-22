<?php

declare(strict_types=1);

namespace App\Identity\UI\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO de request para registro de usuário.
 */
final readonly class RegisterUserRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'CPF is required.')]
        #[Assert\Length(min: 11, max: 14, minMessage: 'CPF must have 11 digits.', maxMessage: 'CPF must have 11 digits.')]
        public string $cpf,

        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'Invalid email format.')]
        public string $email,

        #[Assert\NotBlank(message: 'Name is required.')]
        #[Assert\Length(min: 2, max: 255, minMessage: 'Name must have at least 2 characters.', maxMessage: 'Name must not exceed 255 characters.')]
        public string $name,

        #[Assert\NotBlank(message: 'Password is required.')]
        #[Assert\Length(min: 8, max: 255, minMessage: 'Password must have at least 8 characters.', maxMessage: 'Password must not exceed 255 characters.')]
        public string $password,
    ) {
    }
}
