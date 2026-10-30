<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

/**
 * Exemplo nomeado de uma resposta da API.
 *
 * O nome do exemplo (a chave em `ApiErrorResponse` ou `SuccessResponse`) é o
 * rótulo exibido na documentação, enquanto `summary` e `description` são o
 * título e o texto explicativo do exemplo.
 */
final readonly class ApiExample
{
    /**
     * @param string                    $summary     resumo exibido na documentação
     * @param string                    $description condição ou contexto do exemplo
     * @param array<string, mixed>|null $value       corpo completo; `null` deriva do schema da resposta
     */
    public function __construct(
        public string $summary,
        public string $description,
        public ?array $value = null,
    ) {
    }
}
