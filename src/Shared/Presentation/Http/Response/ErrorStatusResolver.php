<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Response;

use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\ErrorKind;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve o status HTTP a partir da natureza dos erros de domínio.
 *
 * Regra de precedência: `Authentication` vence `Forbidden`, que vence
 * `Validation`, que vence `Conflict`. Quando um Result acumula mais de um tipo,
 * o cliente precisa resolver primeiro a barreira mais forte; o corpo continua
 * trazendo todos os erros.
 */
final class ErrorStatusResolver
{
    /**
     * @param list<DomainError> $errors
     */
    public function fromErrors(array $errors): int
    {
        if ([] === $errors) {
            return $this->fromKind(ErrorKind::Validation);
        }

        $kind = $errors[0]->kind;

        foreach ($errors as $error) {
            if ($this->priority($error->kind) > $this->priority($kind)) {
                $kind = $error->kind;
            }
        }

        return $this->fromKind($kind);
    }

    public function fromKind(ErrorKind $kind): int
    {
        return match ($kind) {
            ErrorKind::Authentication => Response::HTTP_UNAUTHORIZED,
            ErrorKind::Forbidden => Response::HTTP_FORBIDDEN,
            ErrorKind::Validation => Response::HTTP_UNPROCESSABLE_ENTITY,
            ErrorKind::Conflict => Response::HTTP_CONFLICT,
        };
    }

    private function priority(ErrorKind $kind): int
    {
        return match ($kind) {
            ErrorKind::Authentication => 30,
            ErrorKind::Forbidden => 20,
            ErrorKind::Validation => 10,
            ErrorKind::Conflict => 0,
        };
    }
}
