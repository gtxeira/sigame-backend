<?php

declare(strict_types=1);

namespace Tests\Helpers;

use JsonException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
     * @param array<string, mixed>        $server
     */
    public function get(string $route, array|object $payload = [], array $routeParams = [], array $server = []): Response
    {
        return $this->sendRequest($route, $payload, 'GET', $routeParams, $server);
    }

    /**
     * Envia uma requisição POST para a rota (em memória).
     *
     * @param array<string, mixed>|object $payload
     * @param array<string, mixed>        $routeParams
     * @param array<string, mixed>        $server
     */
    public function post(string $route, array|object $payload = [], array $routeParams = [], array $server = []): Response
    {
        return $this->sendRequest($route, $payload, 'POST', $routeParams, $server);
    }

    /**
     * Envia uma requisição PATCH para a rota (em memória).
     *
     * @param array<string, mixed>|object $payload
     * @param array<string, mixed>        $routeParams
     * @param array<string, mixed>        $server
     */
    public function patch(string $route, array|object $payload = [], array $routeParams = [], array $server = []): Response
    {
        return $this->sendRequest($route, $payload, 'PATCH', $routeParams, $server);
    }

    /**
     * Envia uma requisição PUT para a rota (em memória).
     *
     * @param array<string, mixed>|object $payload
     * @param array<string, mixed>        $routeParams
     * @param array<string, mixed>        $server
     */
    public function put(string $route, array|object $payload = [], array $routeParams = [], array $server = []): Response
    {
        return $this->sendRequest($route, $payload, 'PUT', $routeParams, $server);
    }

    /**
     * Envia uma requisição DELETE para a rota (em memória).
     *
     * @param array<string, mixed>|object $payload
     * @param array<string, mixed>        $routeParams
     * @param array<string, mixed>        $server
     */
    public function delete(string $route, array|object $payload = [], array $routeParams = [], array $server = []): Response
    {
        return $this->sendRequest($route, $payload, 'DELETE', $routeParams, $server);
    }

    /**
     * Envia um POST multipart/form-data para a rota (em memória).
     *
     * @param array<string, UploadedFile|list<UploadedFile>> $files
     * @param array<string, mixed>                           $parameters
     * @param array<string, mixed>                           $routeParams
     * @param array<string, mixed>                           $server
     */
    public function postMultipart(
        string $route,
        array $files = [],
        array $parameters = [],
        array $routeParams = [],
        array $server = [],
    ): Response {
        $uri = $this->router->generate($route, $routeParams, UrlGeneratorInterface::ABSOLUTE_PATH);
        $this->client->request('POST', $uri, $parameters, $files, $server);

        return $this->client->getResponse();
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
     * Códigos de erro da última resposta, na ordem em que vieram.
     *
     * @return list<string>
     */
    public function errorCodes(): array
    {
        $content = (string) $this->client->getResponse()->getContent();

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($decoded) || !isset($decoded['errors']) || !is_array($decoded['errors'])) {
            return [];
        }

        $codes = [];

        foreach ($decoded['errors'] as $error) {
            if (is_array($error) && isset($error['code']) && is_string($error['code'])) {
                $codes[] = $error['code'];
            }
        }

        return $codes;
    }

    /**
     * Envia uma requisição para a rota nomeada (em memória).
     *
     * @param array<string, mixed>|object         $payload
     * @param 'GET'|'POST'|'PUT'|'PATCH'|'DELETE' $method
     * @param array<string, mixed>                $routeParams
     * @param array<string, mixed>                $server
     */
    private function sendRequest(
        string $route,
        array|object $payload,
        string $method,
        array $routeParams = [],
        array $server = [],
    ): Response {
        $uri = $this->router->generate($route, $routeParams, UrlGeneratorInterface::ABSOLUTE_PATH);
        $parameters = $this->normalize($payload);

        if ('GET' === $method) {
            $this->client->request($method, $uri, $parameters, [], $server);
        } else {
            $this->client->jsonRequest($method, $uri, $parameters, $server);
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
