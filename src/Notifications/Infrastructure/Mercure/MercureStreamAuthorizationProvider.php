<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Mercure;

use App\Notifications\Application\Service\RealtimeAuthorization;
use App\Notifications\Application\Service\StreamAuthorizationProviderInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\ProtocolVersion;

/**
 * Emite um token de assinatura de curta duração para o tópico do próprio usuário.
 *
 * O token só contém a ação `subscribe`; publicação continua restrita ao servidor.
 */
final readonly class MercureStreamAuthorizationProvider implements StreamAuthorizationProviderInterface
{
    public function __construct(
        private HubInterface $hub,
        #[Autowire(env: 'MERCURE_JWT_SECRET')]
        private string $secret,
        #[Autowire(env: 'MERCURE_TOPIC_BASE')]
        private string $topicBase,
        #[Autowire(env: 'int:MERCURE_SUBSCRIBER_TOKEN_TTL')]
        private int $tokenTtl,
    ) {
    }

    public function authorize(string $userId): RealtimeAuthorization
    {
        $topic = $this->topicFor($userId);
        $protocol = $this->hub->getProtocolVersion();
        $factory = new LcobucciFactory(
            secret: $this->secret,
            jwtLifetime: $this->tokenTtl,
            protocolVersion: $protocol,
        );

        $token = $factory->create([new Grant([Grant::ACTION_SUBSCRIBE], [$topic])]);
        $expiresAt = (new DateTimeImmutable('+'.$this->tokenTtl.' seconds'))->format(DateTimeInterface::RFC3339_EXTENDED);

        return new RealtimeAuthorization(
            transport: 'mercure',
            subscribeUrl: $this->subscribeUrl($topic, $protocol),
            token: $token,
            expiresAt: $expiresAt,
        );
    }

    public function topicFor(string $userId): string
    {
        return rtrim($this->topicBase, '/').'/'.$userId;
    }

    private function subscribeUrl(string $topic, ProtocolVersion $protocol): string
    {
        $parameter = ProtocolVersion::V1 === $protocol ? 'match' : 'topic';
        $separator = str_contains($this->hub->getPublicUrl(), '?') ? '&' : '?';

        return $this->hub->getPublicUrl().$separator.$parameter.'='.rawurlencode($topic);
    }
}
