<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Controller;

use App\Groups\Application\UseCase\ListGroupMembers\ListGroupMembersHandler;
use App\Groups\Application\UseCase\ListGroupMembers\ListGroupMembersQuery;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Presentation\Http\Response\GroupMemberResponse;
use App\Shared\Application\User\UserMemberProfileReaderInterface;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\ListSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\NotFoundResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\Response\ResultResponder;
use LogicException;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Membros aprovados de um grupo, visíveis para quem participa dele.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Grupos')]
final class GroupMembersController extends AbstractController
{
    public function __construct(
        private readonly ListGroupMembersHandler $listGroupMembersHandler,
        private readonly UserMemberProfileReaderInterface $memberProfiles,
        private readonly ResultResponder $resultResponder,
    ) {
    }

    /**
     * Listar membros aprovados
     *
     * Devolve os membros aprovados (nome, nível, foto, papel) para participantes
     * aprovados do grupo. Dados sensíveis não são expostos.
     */
    #[Route('/groups/{id}/members', name: 'app_group_members', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[ListSuccessResponse(itemModel: GroupMemberResponse::class, description: 'Membros aprovados do grupo.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[InternalErrorResponse]
    public function list(string $id): JsonResponse
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        $result = ($this->listGroupMembersHandler)(new ListGroupMembersQuery(
            groupId: GroupId::fromString($id),
            viewerId: MemberUserId::fromString($user->getUserIdentifier()),
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        /** @var list<Membership> $memberships */
        $memberships = $result->value();
        $profiles = $this->memberProfiles->profiles(
            array_map(static fn (Membership $membership): string => $membership->userId()->value(), $memberships),
        );

        return $this->resultResponder->success(
            $result->map(static fn (array $items): array => array_map(
                static fn (Membership $membership): GroupMemberResponse => GroupMemberResponse::fromEntity(
                    $membership,
                    $profiles[$membership->userId()->value()] ?? null,
                ),
                $items,
            )),
        );
    }
}
