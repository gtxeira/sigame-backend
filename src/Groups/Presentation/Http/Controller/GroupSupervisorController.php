<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Controller;

use App\Groups\Application\UseCase\ClearSupervisor\ClearSupervisorCommand;
use App\Groups\Application\UseCase\ClearSupervisor\ClearSupervisorHandler;
use App\Groups\Application\UseCase\DesignateSupervisor\DesignateSupervisorCommand;
use App\Groups\Application\UseCase\DesignateSupervisor\DesignateSupervisorHandler;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MembershipId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Presentation\Http\Request\DesignateSupervisorRequest;
use App\Groups\Presentation\Http\Response\MembershipResponse;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\NotFoundResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\UnsupportedMediaTypeResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ResultResponder;
use LogicException;
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
 * Designação e remoção do supervisor do grupo.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Grupos')]
final class GroupSupervisorController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-fA-F-]{36}';

    public function __construct(
        private readonly DesignateSupervisorHandler $designateSupervisorHandler,
        private readonly ClearSupervisorHandler $clearSupervisorHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.group_manage')]
        private readonly RateLimiterFactoryInterface $manageLimiter,
    ) {
    }

    /**
     * Designar supervisor
     *
     * Designa um membro aprovado como supervisor. A existência de supervisor
     * é opcional e ele não recebe poderes de gestão de membros na V1.
     */
    #[Route(
        '/groups/{id}/supervisor',
        name: 'app_group_supervisor_designate',
        methods: ['PUT'],
        requirements: ['id' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: MembershipResponse::class, description: 'Supervisor designado.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse(
        examples: [
            'GROUP_ADMIN_REQUIRED' => new ApiErrorExample(
                message: 'Apenas administradores do grupo podem executar esta operação.',
                summary: 'Administrador obrigatório',
                description: 'O usuário autenticado não é administrador aprovado do grupo.',
            ),
        ],
    )]
    #[NotFoundResponse]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['membershipId' => 'A participação é obrigatória.'],
                summary: 'Participação inválida',
                description: 'A participação não foi informada ou não é um UUID válido.',
            ),
            'GROUP_SUPERVISOR_NOT_MEMBER' => new ApiErrorExample(
                message: 'O supervisor deve ser um membro aprovado do grupo.',
                details: ['membershipId' => 'A participação informada não está aprovada.'],
                summary: 'Membro não aprovado',
                description: 'Apenas membros aprovados podem ser designados supervisores.',
            ),
        ],
        fields: ['membershipId' => 'Participação aprovada no grupo.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function designate(
        string $id,
        #[MapRequestPayload] DesignateSupervisorRequest $request,
    ): JsonResponse {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->designateSupervisorHandler)(new DesignateSupervisorCommand(
            groupId: GroupId::fromString($id),
            membershipId: MembershipId::fromString($request->membershipId),
            actorId: $actor,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (Membership $membership): MembershipResponse => MembershipResponse::fromEntity($membership)),
        );
    }

    /**
     * Remover supervisor
     *
     * Remove a designação de supervisor do grupo. A operação é idempotente.
     */
    #[Route(
        '/groups/{id}/supervisor',
        name: 'app_group_supervisor_clear',
        methods: ['DELETE'],
        requirements: ['id' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(
        dataProperties: [
            new OA\Property(property: 'removed', type: 'boolean', example: true),
        ],
        dataRequired: ['removed'],
        description: 'Designação de supervisor removida.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse(
        examples: [
            'GROUP_ADMIN_REQUIRED' => new ApiErrorExample(
                message: 'Apenas administradores do grupo podem executar esta operação.',
                summary: 'Administrador obrigatório',
                description: 'O usuário autenticado não é administrador aprovado do grupo.',
            ),
        ],
    )]
    #[NotFoundResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function clear(string $id): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->clearSupervisorHandler)(new ClearSupervisorCommand(
            groupId: GroupId::fromString($id),
            actorId: $actor,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (bool $removed): array => ['removed' => $removed]),
        );
    }

    private function actorId(): MemberUserId
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        return MemberUserId::fromString($user->getUserIdentifier());
    }
}
