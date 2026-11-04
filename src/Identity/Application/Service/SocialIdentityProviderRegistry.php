<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Domain\User\ValueObject\IdentityProvider;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Seleciona o adapter do provedor social sem que a aplicação conheça as
 * implementações concretas.
 */
final readonly class SocialIdentityProviderRegistry
{
    /**
     * @param iterable<SocialIdentityProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(SocialIdentityProviderInterface::class)]
        private iterable $providers,
    ) {
    }

    public function get(IdentityProvider $provider): ?SocialIdentityProviderInterface
    {
        foreach ($this->providers as $candidate) {
            if ($candidate->provider() === $provider) {
                return $candidate;
            }
        }

        return null;
    }
}
