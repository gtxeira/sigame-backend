<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Controller;

use App\Groups\Application\UseCase\ListMyGroups\ListMyGroupsHandler;
use App\Groups\Application\UseCase\ListMyGroups\ListMyGroupsQuery;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Presentation\Http\Response\GroupSummaryResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\Response\ResultResponder;
use LogicException;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Listagem self-service dos grupos do usuário autenticado.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Minha conta')]
final class MyGroupsController extends AbstractController
{
    public function __construct(
        private readonly ListMyGroupsHandler $listMyGroupsHandler,
        private readonly ResultResponder $resultResponder,
    ) {
    }

    /**
     * Listar meus grupos
     *
     * Lista os grupos em que o usuário autenticado possui participação
     * aprovada.
     */
    #[Route('/users/me/groups', name: 'app_me_groups', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(
        dataItems: new OA\Items(ref: new Model(type: GroupSummaryResponse::class)),
        description: 'Grupos do usuário autenticado.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[InternalErrorResponse]
    public function list(): JsonResponse
    {
        $actor = $this->actorId();
        $result = ($this->listMyGroupsHandler)(new ListMyGroupsQuery($actor));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (array $groups): array => array_map(
                static fn (Group $group): GroupSummaryResponse => GroupSummaryResponse::fromEntity($group, $actor),
                $groups,
            )),
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
