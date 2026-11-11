<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\Storage\ProfilePhotoStorageInterface;
use App\Identity\Application\UseCase\UpdateProfile\UpdateProfileCommand;
use App\Identity\Application\UseCase\UpdateProfile\UpdateProfileHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\UpdateProfileRequest;
use App\Identity\Presentation\Http\Response\CurrentUserResponse;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\UnsupportedMediaTypeResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ResultResponder;
use App\Shared\Presentation\Http\Validation\RequestViolationFactory;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Atualização parcial do perfil do usuário autenticado.
 *
 * A semântica é a de JSON Merge Patch (RFC 7396): campo ausente não é
 * alterado e `null` limpa somente altura e peso. Nome, quando enviado, precisa
 * ter conteúdo.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Minha conta')]
final class MeProfileController extends AbstractController
{
    public function __construct(
        private readonly UpdateProfileHandler $updateProfileHandler,
        private readonly ProfilePhotoStorageInterface $photoStorage,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.user_profile')]
        private readonly RateLimiterFactoryInterface $profileLimiter,
    ) {
    }

    /**
     * Atualizar o perfil
     *
     * Altera somente os campos presentes no corpo. O nome não pode ser nulo ou
     * vazio; altura e peso aceitam `null` para remover o valor.
     */
    #[Route('/users/me/profile', name: 'app_me_profile_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: CurrentUserResponse::class, description: 'Perfil atualizado com sucesso.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'Informe ao menos um campo para atualização.',
                field: 'body',
                summary: 'Erros de validação',
                description: 'O corpo está vazio, o nome é inválido ou um valor tem tipo/formato inválido.',
            ),
            'NUMBER_OUT_OF_RANGE' => new ApiErrorExample(
                message: 'A altura deve estar entre 50 e 250 centímetros.',
                field: 'heightCm',
                additional: [
                    [
                        'code' => 'NUMBER_OUT_OF_RANGE',
                        'field' => 'weightKg',
                        'message' => 'O peso deve estar entre 20 e 300 quilogramas.',
                    ],
                ],
                summary: 'Valor fora da faixa',
                description: 'A altura ou o peso estão fora da faixa aceita.',
            ),
        ],
        fields: [
            'body' => 'Corpo da requisição sem campos conhecidos.',
            'name' => 'Nome do usuário.',
            'heightCm' => 'Altura em centímetros.',
            'weightKg' => 'Peso em quilogramas.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function update(
        #[MapRequestPayload(mapWhenEmpty: true)] UpdateProfileRequest $request,
        HttpRequest $httpRequest,
    ): JsonResponse {
        $payload = $httpRequest->getPayload();
        $this->assertPayloadHasChanges($payload->has('name'), $payload->has('heightCm'), $payload->has('weightKg'));

        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        $user = $securityUser->user();
        $this->rateLimitGuard->assertWithin($this->profileLimiter, $user->id()->value());

        $result = ($this->updateProfileHandler)(new UpdateProfileCommand(
            userId: $user->id(),
            nameProvided: $payload->has('name'),
            name: $request->name,
            heightProvided: $payload->has('heightCm'),
            heightCm: $request->heightCm,
            weightProvided: $payload->has('weightKg'),
            weightKg: $request->weightKg,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(fn (User $updated): CurrentUserResponse => CurrentUserResponse::fromEntity(
                user: $updated,
                photoUrl: $this->photoStorage->url($updated->photoKey()),
            )),
        );
    }

    private function assertPayloadHasChanges(
        bool $hasName,
        bool $hasHeight,
        bool $hasWeight,
    ): void {
        if (!$hasName && !$hasHeight && !$hasWeight) {
            throw RequestViolationFactory::unprocessable('FIELD_REQUIRED', 'body', 'Informe ao menos um campo para atualização.');
        }
    }
}
