<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Controller;

use App\Groups\Application\UseCase\TransferOwnership\TransferOwnershipCommand;
use App\Groups\Application\UseCase\TransferOwnership\TransferOwnershipHandler;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MembershipId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Presentation\Http\Request\TransferOwnershipRequest;
use App\Groups\Presentation\Http\Response\GroupResponse;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\NotFoundResponse;
use App\Shared\Presentation\Http\OpenApi\ResourceConflictResponse;
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
 * Transferência da responsabilidade pelo grupo.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Grupos')]
final class GroupOwnershipController extends AbstractController
{
    private const string UUID_REQUIREMENT = '[0-9a-fA-F-]{36}';

    public function __construct(
        private readonly TransferOwnershipHandler $transferOwnershipHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.group_manage')]
        private readonly RateLimiterFactoryInterface $manageLimiter,
    ) {
    }

    /**
     * Transferir responsabilidade
     *
     * Transfere a responsabilidade pelo grupo para um membro aprovado, que é
     * promovido a administrador. Apenas o responsável atual pode transferir.
     */
    #[Route(
        '/groups/{id}/owner',
        name: 'app_group_ownership_transfer',
        methods: ['PUT'],
        requirements: ['id' => self::UUID_REQUIREMENT],
    )]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: GroupResponse::class, description: 'Responsabilidade transferida.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse(
        examples: [
            'GROUP_OWNER_REQUIRED' => new ApiErrorExample(
                message: 'Apenas o responsável pelo grupo pode executar esta operação.',
                summary: 'Responsável obrigatório',
                description: 'O usuário autenticado não é o responsável atual pelo grupo.',
            ),
        ],
    )]
    #[NotFoundResponse]
    #[ResourceConflictResponse(
        examples: [
            'GROUP_MEMBERSHIP_NOT_APPROVED' => new ApiErrorExample(
                message: 'A participação não está aprovada.',
                summary: 'Participação não aprovada',
                description: 'Apenas membros aprovados podem assumir a responsabilidade.',
            ),
            'GROUP_MEMBERSHIP_SUSPENDED' => new ApiErrorExample(
                message: 'A conta do novo responsável está suspensa.',
                field: 'membershipId',
                summary: 'Conta suspensa',
                description: 'O novo responsável precisa estar com a conta ativa.',
            ),
        ],
        fields: ['membershipId' => 'Participação aprovada no grupo.'],
    )]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'A participação é obrigatória.',
                field: 'membershipId',
                summary: 'Participação inválida',
                description: 'A participação não foi informada ou não é um UUID válido.',
            ),
        ],
        fields: ['membershipId' => 'Participação aprovada no grupo.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function transfer(
        string $id,
        #[MapRequestPayload] TransferOwnershipRequest $request,
    ): JsonResponse {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->transferOwnershipHandler)(new TransferOwnershipCommand(
            groupId: GroupId::fromString($id),
            membershipId: MembershipId::fromString($request->membershipId),
            actorId: $actor,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(fn (Group $group): GroupResponse => GroupResponse::fromEntity($group, $actor)),
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
