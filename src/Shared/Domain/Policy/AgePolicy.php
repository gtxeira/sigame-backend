<?php

declare(strict_types=1);

namespace App\Shared\Domain\Policy;

/**
 * Política de idade mínima da plataforma.
 *
 * O limite é decisão de produto/jurídico, não configuração de ambiente:
 * alterá-lo exige revisão da documentação e dos testes de faixa etária.
 */
final class AgePolicy
{
    public const int MINIMUM_AGE = 18;

    private function __construct()
    {
    }
}
