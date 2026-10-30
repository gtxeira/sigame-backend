<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

/**
 * Exemplo nomeado de uma resposta de erro.
 *
 * O nome do exemplo em `ApiErrorResponse` é o próprio código do erro,
 * de forma que o contrato documentado e o corpo retornado usem o mesmo valor.
 */
final readonly class ApiErrorExample
{
    /**
     * @param string               $summary resumo exibido na documentação
     * @param string               $when    condição que provoca o erro
     * @param array<string, mixed> $value   corpo completo da resposta de exemplo
     */
    public function __construct(
        public string $summary,
        public string $when,
        public array $value,
    ) {
    }
}
