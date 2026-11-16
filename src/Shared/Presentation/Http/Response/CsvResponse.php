<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Response;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resposta CSV transmitida em streaming.
 *
 * Usa `;` como separador (Excel pt-BR) e BOM UTF-8 para preservar acentos.
 * O conteúdo é escrito sob demanda, sem montar a planilha inteira em memória.
 */
final class CsvResponse
{
    /**
     * @param list<string>                                      $header
     * @param callable(): iterable<list<string|int|float|null>> $rows
     */
    public static function stream(string $filename, array $header, callable $rows): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($header, $rows): void {
            $handle = fopen('php://output', 'wb');

            if (false === $handle) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $header, ';', '"', '\\');

            foreach ($rows() as $row) {
                fputcsv($handle, $row, ';', '"', '\\');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
