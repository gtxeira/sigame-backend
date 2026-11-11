<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

/**
 * Exemplo nomeado de um erro da API.
 *
 * O `code` não é informado aqui: ele vem da chave do array de exemplos, de
 * forma que o contrato documentado e o corpo do exemplo nunca divergem.
 * Itens adicionais permitem documentar respostas com múltiplos erros.
 */
final readonly class ApiErrorExample
{
    /**
     * @param string                                                     $message     mensagem pública do erro
     * @param string|null                                                $field       campo afetado
     * @param list<array{code: string, field?: string, message: string}> $additional  erros adicionais do mesmo exemplo
     * @param string|null                                                $summary     resumo exibido na documentação
     * @param string|null                                                $description contexto do exemplo
     */
    public function __construct(
        public string $message,
        public ?string $field = null,
        public array $additional = [],
        public ?string $summary = null,
        public ?string $description = null,
    ) {
    }

    /**
     * @return list<array{code: string, field?: string, message: string}>
     */
    public function toErrors(string $code): array
    {
        $errors = [self::item($code, $this->message, $this->field)];

        foreach ($this->additional as $additional) {
            $errors[] = self::item(
                $additional['code'],
                $additional['message'],
                $additional['field'] ?? null,
            );
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public function codes(string $primaryCode): array
    {
        $codes = [$primaryCode];

        foreach ($this->additional as $additional) {
            if (!in_array($additional['code'], $codes, true)) {
                $codes[] = $additional['code'];
            }
        }

        return $codes;
    }

    /**
     * @return list<string>
     */
    public function detailFields(): array
    {
        $fields = null === $this->field ? [] : [$this->field];

        foreach ($this->additional as $additional) {
            $field = $additional['field'] ?? null;

            if (null !== $field && !in_array($field, $fields, true)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @return array{code: string, field?: string, message: string}
     */
    private static function item(string $code, string $message, ?string $field): array
    {
        $item = ['code' => $code, 'message' => $message];

        if (null !== $field && '' !== $field) {
            $item = ['code' => $code, 'field' => $field, 'message' => $message];
        }

        return $item;
    }
}
