<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Crypto\Exception;

use RuntimeException;

/**
 * Lançada quando um payload cifrado não pode ser aberto.
 */
final class PayloadDecryptionFailed extends RuntimeException
{
}
