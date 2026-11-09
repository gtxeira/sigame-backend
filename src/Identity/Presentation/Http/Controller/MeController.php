<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\Storage\ProfilePhotoStorageInterface;
use App\Identity\Application\UseCase\AssignCpf\AssignCpfCommand;
use App\Identity\Application\UseCase\AssignCpf\AssignCpfHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\AssignCpfRequest;
use App\Identity\Presentation\Http\Response\CurrentUserResponse;
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
use App\Shared\Presentation\Http\Response\ApiResponse;
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
 * Dados do usuário autenticado.
 *
 * Quando existir um CRUD administrativo em `/api/v1/users/{id}`, a rota `me`
 * precisa ser declarada antes ou `{id}` precisa de um requirement que não
 * aceite o literal `me`. A tag `Minha conta` separa estas ações self-service
 * da gestão administrativa de usuários.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Minha conta')]
final class MeController extends AbstractController
{
    public function __construct(
        private readonly AssignCpfHandler $assignCpfHandler,
        private readonly ProfilePhotoStorageInterface $photoStorage,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.user_cpf')]
        private readonly RateLimiterFactoryInterface $cpfLimiter,
    ) {
    }

    /**
     * Obter os dados do usuário autenticado
     *
     * Retorna os dados públicos da conta associada ao access token enviado no
     * header Authorization.
     */
    #[Route('/users/me', name: 'app_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: CurrentUserResponse::class, description: 'Dados do usuário autenticado.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[InternalErrorResponse]
    public function me(): JsonResponse
    {
        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        $user = $securityUser->user();

        return ApiResponse::ok(
            CurrentUserResponse::fromEntity(
                user: $user,
                photoUrl: $this->photoStorage->url($user->photoKey()),
            ),
        )->toJsonResponse();
    }

    /**
     * Definir o CPF do usuário autenticado
     *
     * O CPF pode ser informado uma única vez (write-once) e não pode ser
     * alterado pelo próprio usuário depois de definido. A operação é limitada
     * por usuário.
     */
    #[Route('/users/me/cpf', name: 'app_me_cpf', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: CurrentUserResponse::class, description: 'CPF definido com sucesso.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[ResourceConflictResponse(
        examples: [
            'USER_CPF_ALREADY_SET' => new ApiErrorExample(
                message: 'O CPF já foi definido e não pode ser alterado.',
                details: ['cpf'],
                summary: 'CPF já definido',
                description: 'O CPF é write-once: uma vez definido, o próprio usuário não pode alterá-lo.',
            ),
            'USER_CPF_ALREADY_EXISTS' => new ApiErrorExample(
                message: 'Já existe um usuário com este CPF.',
                details: ['cpf'],
                summary: 'CPF em uso',
                description: 'O CPF informado pertence a outro usuário.',
            ),
        ],
        fields: ['cpf' => 'CPF do usuário.'],
    )]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['cpf' => 'O CPF é obrigatório.'],
                summary: 'Erros de Validação',
                description: 'O CPF não foi informado ou está em formato inválido.',
            ),
            'CPF_INVALID' => new ApiErrorExample(
                message: 'O CPF possui dígitos verificadores inválidos.',
                details: ['cpf'],
                summary: 'CPF inválido',
                description: 'O CPF não passou na validação de dígitos verificadores.',
            ),
        ],
        fields: ['cpf' => 'CPF do usuário.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function assignCpf(
        #[MapRequestPayload] AssignCpfRequest $request,
    ): JsonResponse {
        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        $user = $securityUser->user();
        $this->rateLimitGuard->assertWithin($this->cpfLimiter, $user->id()->value());

        $result = ($this->assignCpfHandler)(new AssignCpfCommand(
            userId: $user->id(),
            cpf: $request->cpf,
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
