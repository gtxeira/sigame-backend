<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer;

use App\Shared\Domain\ValueObject\Instant;
use DateTimeZone;

/**
 * Formata datas exibidas nos e-mails de identidade.
 *
 * O domínio trabalha sempre em UTC; o usuário final lê as datas no fuso de
 * Brasília. Centralizar aqui evita divergência entre o adapter real e os
 * dublês de teste.
 */
final class MailDateFormatter
{
    public const string TIMEZONE = 'America/Sao_Paulo';
    public const string FORMAT = 'd/m/Y H:i';

    public static function format(Instant $instant): string
    {
        return $instant->formatInTimezone(new DateTimeZone(self::TIMEZONE), self::FORMAT);
    }
}
