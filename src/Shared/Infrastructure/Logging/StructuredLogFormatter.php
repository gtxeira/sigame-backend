<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use DateTimeInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use stdClass;

/**
 * JSON neutro, consumível por Dozzle, Loki, Datadog, ELK etc.
 *
 * {"time":"...","level":"debug","message":"...","channel":"doctrine","context":{},"extra":{}}
 */
final class StructuredLogFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        return $this->toJson([
            'time' => $record->datetime->format(DateTimeInterface::RFC3339_EXTENDED),
            'level' => $record->level->toPsrLogLevel(),
            'message' => $record->message,
            'channel' => $record->channel,
            'context' => [] === $record->context ? new stdClass() : $this->normalizeValue($record->context),
            'extra' => [] === $record->extra ? new stdClass() : $this->normalizeValue($record->extra),
        ], true)."\n";
    }
}
