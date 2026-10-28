<?php

declare(strict_types=1);

namespace Tests\Helpers;

use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class ApiClient
{
    public function __construct(
        private KernelBrowser $client,
        private SerializerInterface|NormalizerInterface $serializer,
        private RouterInterface $router,
        private string $apiDomain,
    ) {
    }

    /**
     * Envia uma requisição GET para o controller (em memória).
     *
     * @param class-string|array{class-string, string} $controllerAction Ex: RegistrationController::class ou [UserController::class, 'show']
     * @param array<string, mixed>|object              $payload
     */
    public function get(string|array $controllerAction, array|object $payload): Response
    {
        return $this->sendRequest($controllerAction, $payload);
    }

    /**
     * Envia uma requisição POST para o controller (em memória).
     *
     * @param class-string|array{class-string, string} $controllerAction Ex: RegistrationController::class ou [UserController::class, 'show']
     * @param array<string, mixed>|object              $payload
     */
    public function post(string|array $controllerAction, array|object $payload): Response
    {
        return $this->sendRequest($controllerAction, $payload, 'POST');
    }

    /**
     * Envia uma requisição PATCH para o controller (em memória).
     *
     * @param class-string|array{class-string, string} $controllerAction Ex: RegistrationController::class ou [UserController::class, 'show']
     * @param array<string, mixed>|object              $payload
     */
    public function patch(string|array $controllerAction, array|object $payload): Response
    {
        return $this->sendRequest($controllerAction, $payload, 'PATCH');
    }

    /**
     * Envia uma requisição PUT para o controller (em memória).
     *
     * @param class-string|array{class-string, string} $controllerAction Ex: RegistrationController::class ou [UserController::class, 'show']
     * @param array<string, mixed>|object              $payload
     */
    public function put(string|array $controllerAction, array|object $payload): Response
    {
        return $this->sendRequest($controllerAction, $payload, 'PUT');
    }

    /**
     * Envia uma requisição DELETE para o controller (em memória).
     *
     * @param class-string|array{class-string, string} $controllerAction Ex: RegistrationController::class ou [UserController::class, 'show']
     * @param array<string, mixed>|object              $payload
     */
    public function delete(string|array $controllerAction, array|object $payload): Response
    {
        return $this->sendRequest($controllerAction, $payload, 'DELETE');
    }

    /**
     * Envia uma requisição para o controller (em memória).
     *
     * @param class-string|array{class-string, string} $controllerAction Ex: RegistrationController::class ou [UserController::class, 'show']
     * @param array<string, mixed>|object              $payload
     * @param 'GET'|'POST'|'PUT'|'PATCH'|'DELETE'|null $method
     *
     * @throws void
     */
    public function sendRequest(string|array $controllerAction, array|object $payload, ?string $method = 'GET'): Response
    {
        // TODO: Terminar lógica de obter o método http direto do controllerAction
        /** @phpstan-ignore-next-line */
        $arrayPayload = $this->serializer->normalize($payload);
        $uri = $this->resolveUriFromController($controllerAction, $arrayPayload);

        $this->client->jsonRequest(
            method: $method ?? 'GET',
            uri: $uri,
            parameters: $arrayPayload,
        );

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
     * Localiza a rota no Symfony Router correspondente ao Controller/Método informado.
     *
     * @param class-string|array{class-string, string} $controllerAction
     * @param array<string, mixed>                     $urlParameters
     */
    private function resolveUriFromController(string|array $controllerAction, array $urlParameters = []): string
    {
        // Converte array [Controller::class, 'method'] ou string em formato 'Class::method' ou 'Class'
        $targetController = match (true) {
            is_array($controllerAction) => sprintf('%s::%s', $controllerAction[0], $controllerAction[1]),
            class_exists($controllerAction) && method_exists($controllerAction, '__invoke') => sprintf('%s::__invoke', $controllerAction),
            default => $controllerAction,
        };

        foreach ($this->router->getRouteCollection() as $routeName => $route) {
            $controllerAttr = $route->getDefault('_controller');

            if ($controllerAttr === $targetController || $controllerAttr === $controllerAction) {
                return $this->router->generate($routeName, $urlParameters)
                    |> (fn (string $route) => str_replace($this->apiDomain, '', $route));
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Nenhuma rota cadastrada foi encontrada para o Controller/Ação "%s".',
            is_array($controllerAction) ? implode('::', $controllerAction) : $controllerAction,
        ));
    }
}
