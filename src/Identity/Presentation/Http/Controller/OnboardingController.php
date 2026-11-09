<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\Storage\ProfilePhotoStorageInterface;
use App\Identity\Application\UseCase\CompleteOnboarding\CompleteOnboardingCommand;
use App\Identity\Application\UseCase\CompleteOnboarding\CompleteOnboardingHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\CompleteOnboardingRequest;
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
 * Conclusão do onboarding do usuário autenticado.
 *
 * A conclusão é única: depois de concluído, novas tentativas retornam
 * `409 ONBOARDING_ALREADY_COMPLETED`, sem conceder novamente o nível 2.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Onboarding')]
final class OnboardingController extends AbstractController
{
    public function __construct(
        private readonly CompleteOnboardingHandler $completeOnboardingHandler,
        private readonly ProfilePhotoStorageInterface $photoStorage,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.user_onboarding')]
        private readonly RateLimiterFactoryInterface $onboardingLimiter,
    ) {
    }

    /**
     * Concluir onboarding
     *
     * Define os dados mínimos de perfil do usuário autenticado e o promove ao
     * nível 2. A operação pode ser executada uma única vez; tentativas
     * posteriores são recusadas com conflito.
     */
    #[Route('/users/me/onboarding', name: 'app_me_onboarding', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(
        dataModel: CurrentUserResponse::class,
        description: 'Onboarding concluído; o usuário alcançou o nível 2.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[ResourceConflictResponse(
        examples: [
            'ONBOARDING_ALREADY_COMPLETED' => new ApiErrorExample(
                message: 'O onboarding já foi concluído.',
                summary: 'Onboarding já concluído',
                description: 'A conclusão é única e não concede novamente o nível 2.',
            ),
        ],
    )]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['modalities' => 'A modalidade informada é inválida.'],
                summary: 'Erros de Validação',
                description: 'O payload contém dados com tipo, formato ou valor inválido.',
            ),
            'ONBOARDING_NO_MODALITY_SELECTED' => new ApiErrorExample(
                message: 'Pelo menos uma modalidade deve ser selecionada.',
                details: ['modalities'],
                summary: 'Nenhuma modalidade',
                description: 'O onboarding exige ao menos uma modalidade ativa.',
            ),
            'ONBOARDING_INVALID_PRIMARY_MODALITY' => new ApiErrorExample(
                message: 'A modalidade principal deve ser uma das modalidades selecionadas.',
                details: ['primaryModality'],
                summary: 'Modalidade principal inválida',
                description: 'A modalidade principal informada não está entre as modalidades selecionadas.',
            ),
        ],
        fields: [
            'modalities' => 'Modalidades praticadas.',
            'primaryModality' => 'Modalidade principal.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function complete(
        #[MapRequestPayload] CompleteOnboardingRequest $request,
    ): JsonResponse {
        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        $user = $securityUser->user();
        $this->rateLimitGuard->assertWithin($this->onboardingLimiter, $user->id()->value());

        $result = ($this->completeOnboardingHandler)(new CompleteOnboardingCommand(
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
