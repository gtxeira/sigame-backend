<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class ApiClient
{
    public function __construct(
        private KernelBrowser $client,
        private SerializerInterface|NormalizerInterface $serializer,
        private RouterInterface $router,
    ) {
    }

    /**
     * Envia uma requisição GET para a rota (em memória). O payload vira query string.
     *
     * @param array<string, mixed>|object $payload
     * @param array<string, mixed>        $routeParams
     */
    public function get(string $route, array|object $payload = [], array $routeParams = []): Response
    {
        return $this->sendRequest($route, $payload, 'GET', $routeParams);
    }

    /**
     * Envia uma requisição POST para a rota (em memória).
     *
     * @param array<string, mixed>|object $payload
     * @param array<string, mixed>        $routeParams
     */
    public function post(string $route, array|object $payload = [], array $routeParams = []): Response
    {
        return $this->sendRequest($route, $payload, 'POST', $routeParams);
    }

    /**
     * Envia uma requisição PATCH para a rota (em memória).
     *
     * @param array<string, mixed>|object $payload
     * @param array<string, mixed>        $routeParams
     */
    public function patch(string $route, array|object $payload = [], array $routeParams = []): Response
    {
        return $this->sendRequest($route, $payload, 'PATCH', $routeParams);
    }

    /**
     * Envia uma requisição PUT para a rota (em memória).
     *
     * @param array<string, mixed>|object $payload
     * @param array<string, mixed>        $routeParams
     */
    public function put(string $route, array|object $payload = [], array $routeParams = []): Response
    {
        return $this->sendRequest($route, $payload, 'PUT', $routeParams);
    }

    /**
     * Envia uma requisição DELETE para a rota (em memória).
     *
     * @param array<string, mixed>|object $payload
     * @param array<string, mixed>        $routeParams
     */
    public function delete(string $route, array|object $payload = [], array $routeParams = []): Response
    {
        return $this->sendRequest($route, $payload, 'DELETE', $routeParams);
    }

    /**
     * Desserializa o corpo da resposta HTTP para um DTO tipado.
     *
     * @template T of object
     *
     * @param class-string<T> $dtoClass
     *
     * @return T
     *
     * @throws void
     */
    public function deserializeResponse(string $dtoClass): object
    {
        $content = (string) $this->client->getResponse()->getContent();

        /**
         * @var array<string, mixed> $decoded
         *
         * @phpstan-ignore-next-line
         */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $dataToDeserialize = $decoded['data'] ?? $decoded;

        /** @var T $result */
        /** @phpstan-ignore-next-line */
        $result = $this->serializer->deserialize(
            /* @phpstan-ignore-next-line */
            json_encode($dataToDeserialize, JSON_THROW_ON_ERROR),
            $dtoClass,
            'json',
        );

        return $result;
    }

    public function getKernelBrowser(): KernelBrowser
    {
        return $this->client;
    }

    /**
     * Envia uma requisição para a rota nomeada (em memória).
     *
     * @param array<string, mixed>|object         $payload
     * @param 'GET'|'POST'|'PUT'|'PATCH'|'DELETE' $method
     * @param array<string, mixed>                $routeParams
     */
    private function sendRequest(string $route, array|object $payload, string $method, array $routeParams = []): Response
    {
        $uri = $this->router->generate($route, $routeParams, UrlGeneratorInterface::ABSOLUTE_PATH);
        $parameters = $this->normalize($payload);

        if ('GET' === $method) {
            $this->client->request($method, $uri, $parameters);
        } else {
            $this->client->jsonRequest($method, $uri, $parameters);
        }

        return $this->client->getResponse();
    }

    /**
     * @param array<string, mixed>|object $payload
     *
     * @return array<string, mixed>
     */
    private function normalize(array|object $payload): array
    {
        /** @var array<string, mixed> $parameters */
        $parameters = $this->serializer->normalize($payload);

        return $parameters;
    }
}
