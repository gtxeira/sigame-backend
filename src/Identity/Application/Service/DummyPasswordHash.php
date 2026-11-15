<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

/**
 * Hash bcrypt válido sem senha correspondente.
 *
 * Usado para executar a mesma KDF quando o e-mail não existe (ou o desafio não
 * está disponível) e evitar enumeração por tempo de resposta.
 */
final class DummyPasswordHash
{
    public const string VALUE = '$2y$12$nTt0pudahZf3SjiV80P5zORv0z7NzlQ.wmGhWR3wyt2dmTnbukuVG';
}
