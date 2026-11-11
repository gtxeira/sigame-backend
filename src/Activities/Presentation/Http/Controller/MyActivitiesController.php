<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Controller;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Application\UseCase\ListMyActivities\ListMyActivitiesHandler;
use App\Activities\Application\UseCase\ListMyActivities\ListMyActivitiesQuery;
use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Repository\ActivityPage;
use App\Activities\Domain\Activity\Repository\ActivitySearchCriteria;
use App\Activities\Domain\Activity\ValueObject\ActivityStatus;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Presentation\Http\Request\ActivityListQuery;
use App\Activities\Presentation\Http\Response\ActivitySummaryResponse;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\PaginatedSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\Response\ApiResponse;
use App\Shared\Presentation\Http\Response\ResultResponder;
use App\Shared\Presentation\Http\Validation\RequestViolationFactory;
use LogicException;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
#[OA\Tag(name: 'Minha conta')]
final class MyActivitiesController extends AbstractController
{
    public function __construct(
        private readonly ListMyActivitiesHandler $listMyActivitiesHandler,
        private readonly ResultResponder $resultResponder,
        private readonly GroupAccessReaderInterface $groupAccessReader,
        private readonly ActivityAccessPolicy $accessPolicy,
    ) {
    }

    /**
     * Listar minhas atividades
     *
     * Lista as atividades em que o usuário autenticado possui inscrição ativa.
     */
    #[Route('/users/me/activities', name: 'app_my_activity_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[PaginatedSuccessResponse(itemModel: ActivitySummaryResponse::class, description: 'Lista paginada de atividades do usuário.')]
    #[UnauthorizedResponse]
    #[ValidationErrorResponse(
        examples: [
            'INVALID_CHOICE' => new ApiErrorExample(
                message: 'O estado informado é inválido.',
                field: 'status',
                summary: 'Filtros inválidos',
                description: 'A paginação, o estado ou o intervalo de datas são inválidos.',
            ),
        ],
        fields: [
            'page' => 'Página solicitada.',
            'perPage' => 'Itens por página.',
            'status' => 'Estado da atividade.',
            'from' => 'Início mínimo.',
            'to' => 'Início máximo.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function list(#[MapQueryString] ActivityListQuery $query): JsonResponse
    {
        $actor = $this->actorId();
        $criteria = new ActivitySearchCriteria(
            page: $query->page,
            perPage: $query->perPage,
            status: null === $query->status ? null : ActivityStatus::from($query->status),
            from: $this->instantOrNull($query->from, 'from'),
            to: $this->instantOrNull($query->to, 'to'),
        );

        $result = ($this->listMyActivitiesHandler)(new ListMyActivitiesQuery(
            userId: $actor,
            criteria: $criteria,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        /** @var ActivityPage $page */
        $page = $result->value();

        return ApiResponse::ok(array_map(
            fn (Activity $activity): ActivitySummaryResponse => ActivitySummaryResponse::fromEntity(
                $activity,
                $actor,
                $this->canManage($activity, $actor),
            ),
            $page->items,
        ))
            ->withPagination($criteria->page, $criteria->perPage, $page->total)
            ->toJsonResponse();
    }

    private function instantOrNull(?string $value, string $field): ?Instant
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $instant = Instant::fromString($value);

        if ($instant->isInvalid()) {
            throw RequestViolationFactory::unprocessable('INVALID_FORMAT', $field, 'Data/hora inválida.');
        }

        return $instant->value();
    }

    private function canManage(Activity $activity, ActivityUserId $actor): bool
    {
        $group = $this->groupAccessReader->snapshot($activity->groupId()->value(), $actor->value());

        return null === $this->accessPolicy->canManage($group, $this->isGranted('ROLE_ADMIN_SIGAME'));
    }

    private function actorId(): ActivityUserId
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        return ActivityUserId::fromString($user->getUserIdentifier());
    }
}
