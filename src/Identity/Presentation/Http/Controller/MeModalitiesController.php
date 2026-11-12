<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\Storage\ProfilePhotoStorageInterface;
use App\Identity\Application\UseCase\UpdateModalities\UpdateModalitiesCommand;
use App\Identity\Application\UseCase\UpdateModalities\UpdateModalitiesHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\UpdateModalitiesRequest;
use App\Identity\Presentation\Http\Response\CurrentUserResponse;
use App\Shared\Domain\ValueObject\Modality;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\ResourceConflictResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\UnsupportedMediaTypeResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ResultResponder;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Alteração das modalidades de um usuário já cadastrado.
 *
 * A conclusão do onboarding é única e concede 100 XP; esta rota apenas atualiza
 * as modalidades depois disso, sem alterar XP ou nível.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Minha conta')]
final class MeModalitiesController extends AbstractController
{
    public function __construct(
        private readonly UpdateModalitiesHandler $updateModalitiesHandler,
        private readonly ProfilePhotoStorageInterface $photoStorage,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.user_profile')]
        private readonly RateLimiterFactoryInterface $profileLimiter,
    ) {
    }

    /**
     * Atualizar as modalidades
     *
     * Substitui o conjunto de modalidades e a modalidade principal. Exige o
     * onboarding já concluído e não concede XP novamente.
     */
    #[Route('/users/me/modalities', name: 'app_me_modalities_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: CurrentUserResponse::class, description: 'Modalidades atualizadas com sucesso.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[ValidationErrorResponse(
        examples: [
            'INVALID_CHOICE' => new ApiErrorExample(
                message: 'A modalidade informada é inválida.',
                field: 'modalities',
                summary: 'Erros de Validação',
                description: 'O payload contém modalidade inválida ou nenhuma modalidade.',
            ),
            'ONBOARDING_NO_MODALITY_SELECTED' => new ApiErrorExample(
                message: 'Pelo menos uma modalidade deve ser selecionada.',
                field: 'modalities',
                summary: 'Nenhuma modalidade',
                description: 'É preciso manter ao menos uma modalidade ativa.',
            ),
            'ONBOARDING_INVALID_PRIMARY_MODALITY' => new ApiErrorExample(
                message: 'A modalidade principal deve ser uma das modalidades selecionadas.',
                field: 'primaryModality',
                summary: 'Modalidade principal inválida',
                description: 'A modalidade principal não está entre as modalidades selecionadas.',
            ),
        ],
        fields: [
            'modalities' => 'Modalidades praticadas.',
            'primaryModality' => 'Modalidade principal.',
        ],
    )]
    #[ResourceConflictResponse(
        examples: [
            'ONBOARDING_NOT_COMPLETED' => new ApiErrorExample(
                message: 'Conclua o onboarding antes de alterar as modalidades.',
                summary: 'Onboarding pendente',
                description: 'Use POST /users/me/onboarding para a primeira definição das modalidades.',
            ),
        ],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function update(
        #[MapRequestPayload] UpdateModalitiesRequest $request,
    ): JsonResponse {
        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        $user = $securityUser->user();
        $this->rateLimitGuard->assertWithin($this->profileLimiter, $user->id()->value());

        $result = ($this->updateModalitiesHandler)(new UpdateModalitiesCommand(
            userId: $user->id(),
            modalities: array_map(
                static fn (string $modality): Modality => Modality::from($modality),
                $request->modalities,
            ),
            primaryModality: null === $request->primaryModality
                ? null
                : Modality::from($request->primaryModality),
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
}
