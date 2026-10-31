<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

/**
 * Exemplo nomeado de um erro da API.
 *
 * O `code` não é informado aqui: ele vem da chave do array de exemplos, de
 * forma que o contrato documentado e o corpo do exemplo nunca divergem. O
 * envelope `{"errors": [...]}` também é montado pelo `ApiErrorResponse`.
 */
final readonly class ApiErrorExample
{
    /**
     * @param string                                  $message     mensagem pública do erro
     * @param array<string, string|null>|list<string> $details     lista de campos (sem mensagem)
     *                                                             ou mapa field => mensagem|null
     * @param string|null                             $summary     resumo exibido na documentação
     * @param string|null                             $description contexto do exemplo
     */
    public function __construct(
        public string $message,
        public array $details = [],
        public ?string $summary = null,
        public ?string $description = null,
    ) {
    }

    /**
     * @return array{code: string, message: string, details?: list<array{field: string, message?: string}>}
     */
    public function toError(string $code): array
    {
        $error = [
            'code' => $code,
            'message' => $this->message,
        ];

        if ([] === $this->details) {
            return $error;
        }

        $details = [];

        if (array_is_list($this->details)) {
            foreach ($this->details as $field) {
                $details[] = ['field' => $field];
            }
        } else {
            foreach ($this->details as $field => $message) {
                $detail = ['field' => $field];

                if (null !== $message) {
                    $detail['message'] = $message;
                }

                $details[] = $detail;
            }
        }

        $error['details'] = $details;

        return $error;
    }

    /**
     * @return list<string>
     */
    public function detailFields(): array
    {
        if (array_is_list($this->details)) {
            return $this->details;
        }

        return array_keys($this->details);
    }
}
