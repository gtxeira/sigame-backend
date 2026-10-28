<?php

declare(strict_types=1);

namespace Tests\Helpers\Factories;

use App\Identity\Presentation\Http\Request\RegisterUserRequest;
use Zenstruck\Foundry\ObjectFactory;

/**
 * @extends ObjectFactory<RegisterUserRequest>
 */
final class RegisterUserRequestFactory extends ObjectFactory
{
    use HasBrFakerTrait;

    public static function class(): string
    {
        return RegisterUserRequest::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'cpf' => self::faker()->cpf(), // 11 dígitos numéricos
            'email' => self::faker()->unique()->safeEmail(),
            'name' => self::faker()->name(),
            'password' => 'secret1234', // Senha padrão para testes
        ];
    }
}
