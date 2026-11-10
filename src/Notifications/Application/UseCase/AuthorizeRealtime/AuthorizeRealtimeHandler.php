<?php

declare(strict_types=1);

namespace App\Notifications\Application\UseCase\AuthorizeRealtime;

use App\Notifications\Application\Service\RealtimeAuthorization;
use App\Notifications\Application\Service\StreamAuthorizationProviderInterface;
use App\Shared\Domain\Functional\Result;

final readonly class AuthorizeRealtimeHandler
{
    public function __construct(
        private StreamAuthorizationProviderInterface $authorizationProvider,
    ) {
    }

    /**
     * @return Result<RealtimeAuthorization>
     */
    public function __invoke(string $userId): Result
    {
        return Result::success($this->authorizationProvider->authorize($userId));
    }
}
