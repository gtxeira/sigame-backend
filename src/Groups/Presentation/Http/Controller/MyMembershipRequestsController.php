<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Controller;

use App\Groups\Application\UseCase\ListMyMembershipRequests\ListMyMembershipRequestsHandler;
use App\Groups\Application\UseCase\ListMyMembershipRequests\ListMyMembershipRequestsQuery;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Presentation\Http\Response\MyMembershipRequestResponse;
use App\Shared\Application\User\UserContactReaderInterface;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\ListSuccessResponse;
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
 * Listagem self-service das solicitações e participações do usuário.
 *
 * Alimenta o bloco "Minhas solicitações de entrada" em `/grupos`: mostra o
 * estado (pendente, aprovada, recusada, removida) e quem decidiu.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Minha conta')]
final class MyMembershipRequestsController extends AbstractController
{
    public function __construct(
        private readonly ListMyMembershipRequestsHandler $listMyMembershipRequestsHandler,
        private readonly UserContactReaderInterface $userContacts,
        private readonly ResultResponder $resultResponder,
    ) {
    }

    /**
     * Listar minhas solicitações de entrada
     *
     * Retorna as participações do usuário autenticado, das mais recentes para
     * as mais antigas. Grupos excluídos não aparecem.
     */
    #[Route('/users/me/membership-requests', name: 'app_me_membership_requests', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[ListSuccessResponse(
        itemModel: MyMembershipRequestResponse::class,
        description: 'Solicitações e participações do usuário autenticado.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[InternalErrorResponse]
    public function list(): JsonResponse
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        $actor = MemberUserId::fromString($user->getUserIdentifier());
        $result = ($this->listMyMembershipRequestsHandler)(new ListMyMembershipRequestsQuery($actor));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(fn (array $memberships): array => array_map(
                fn (Membership $membership): MyMembershipRequestResponse => MyMembershipRequestResponse::fromEntity(
                    $membership,
                    null === $membership->decidedBy()
                        ? null
                        : $this->userContacts->contact($membership->decidedBy()->value()),
                ),
                $memberships,
            )),
        );
    }
}
