<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\Storage\ProfilePhotoStorageInterface;
use App\Identity\Application\UseCase\AssignBirthDate\AssignBirthDateCommand;
use App\Identity\Application\UseCase\AssignBirthDate\AssignBirthDateHandler;
use App\Identity\Application\UseCase\AssignCpf\AssignCpfCommand;
use App\Identity\Application\UseCase\AssignCpf\AssignCpfHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\AssignBirthDateRequest;
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
        private readonly AssignBirthDateHandler $assignBirthDateHandler,
        private readonly ProfilePhotoStorageInterface $photoStorage,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.user_cpf')]
        private readonly RateLimiterFactoryInterface $cpfLimiter,
        #[Autowire(service: 'limiter.user_birth_date')]
        private readonly RateLimiterFactoryInterface $birthDateLimiter,
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
                field: 'cpf',
                summary: 'CPF já definido',
                description: 'O CPF é write-once: uma vez definido, o próprio usuário não pode alterá-lo.',
            ),
            'USER_CPF_ALREADY_EXISTS' => new ApiErrorExample(
                message: 'Já existe um usuário com este CPF.',
                field: 'cpf',
                summary: 'CPF em uso',
                description: 'O CPF informado pertence a outro usuário.',
            ),
        ],
        fields: ['cpf' => 'CPF do usuário.'],
    )]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'O CPF é obrigatório.',
                field: 'cpf',
                summary: 'Erros de Validação',
                description: 'O CPF não foi informado ou está em formato inválido.',
            ),
            'INVALID_FORMAT' => new ApiErrorExample(
                message: 'O CPF possui dígitos verificadores inválidos.',
                field: 'cpf',
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

    /**
     * Definir a data de nascimento do usuário autenticado
     *
     * A data pode ser informada uma única vez (write-once) e não pode ser
     * alterada pelo próprio usuário depois de definida. É pré-requisito do
     * onboarding e a plataforma é restrita a maiores de 18 anos.
     */
    #[Route('/users/me/birth-date', name: 'app_me_birth_date', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: CurrentUserResponse::class, description: 'Data de nascimento definida com sucesso.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[ResourceConflictResponse(
        examples: [
            'USER_BIRTH_DATE_ALREADY_SET' => new ApiErrorExample(
                message: 'A data de nascimento já foi definida e não pode ser alterada.',
                field: 'birthDate',
                summary: 'Data de nascimento já definida',
                description: 'A data é write-once: uma vez definida, o próprio usuário não pode alterá-la.',
            ),
        ],
        fields: ['birthDate' => 'Data de nascimento no formato AAAA-MM-DD.'],
    )]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'A data de nascimento é obrigatória.',
                field: 'birthDate',
                summary: 'Erros de Validação',
                description: 'A data de nascimento não foi informada.',
            ),
            'INVALID_FORMAT' => new ApiErrorExample(
                message: 'Informe uma data válida no formato AAAA-MM-DD.',
                field: 'birthDate',
                summary: 'Data inválida',
                description: 'A data não existe no calendário ou está em formato diferente de AAAA-MM-DD.',
            ),
            'NUMBER_OUT_OF_RANGE' => new ApiErrorExample(
                message: 'A data de nascimento não pode estar no futuro.',
                field: 'birthDate',
                summary: 'Data fora do intervalo',
                description: 'A data está no futuro ou é anterior a 1900.',
            ),
            'USER_UNDER_MINIMUM_AGE' => new ApiErrorExample(
                message: 'É necessário ter pelo menos 18 anos para usar a plataforma.',
                field: 'birthDate',
                summary: 'Idade mínima',
                description: 'A plataforma é restrita a maiores de 18 anos. Contas incompletas são removidas.',
            ),
        ],
        fields: ['birthDate' => 'Data de nascimento no formato AAAA-MM-DD.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function assignBirthDate(
        #[MapRequestPayload] AssignBirthDateRequest $request,
    ): JsonResponse {
        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        $user = $securityUser->user();
        $this->rateLimitGuard->assertWithin($this->birthDateLimiter, $user->id()->value());

        $result = ($this->assignBirthDateHandler)(new AssignBirthDateCommand(
            userId: $user->id(),
            birthDate: $request->birthDate,
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
