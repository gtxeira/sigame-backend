<?php

declare(strict_types=1);

namespace App\Administration\Presentation\Http\Controller;

use App\Administration\Application\Audit\ListAdminAudit\ListAdminAuditHandler;
use App\Administration\Application\Audit\ListAdminAudit\ListAdminAuditQuery;
use App\Administration\Domain\Audit\Entity\AdminAuditEntry;
use App\Administration\Domain\Audit\Repository\AdminAuditPage;
use App\Administration\Domain\Audit\Repository\AdminAuditSearchCriteria;
use App\Administration\Presentation\Http\Request\AdminAuditListQuery;
use App\Administration\Presentation\Http\Response\AdminAuditEntryResponse;
use App\Shared\Domain\ValueObject\AdminAuditAction;
use App\Shared\Domain\ValueObject\AdminAuditTargetType;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\PaginatedSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ApiResponse;
use App\Shared\Presentation\Http\Response\ResultResponder;
use App\Shared\Presentation\Http\Validation\RequestViolationFactory;
use LogicException;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Trilha de auditoria das ações da administração global.
 */
#[Route('/api/v1/admin')]
#[OA\Tag(name: 'Administração')]
final class AdminAuditController extends AbstractController
{
    public function __construct(
        private readonly ListAdminAuditHandler $listAdminAuditHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.admin_read')]
        private readonly RateLimiterFactoryInterface $readLimiter,
    ) {
    }

    /**
     * Listar a trilha de auditoria
     *
     * Lista as ações administrativas registradas, da mais recente para a mais
     * antiga, com filtros por ação, tipo de alvo, autor e período.
     */
    #[Route('/audit', name: 'app_admin_audit_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN_SIGAME')]
    #[Security(name: 'bearerAuth')]
    #[PaginatedSuccessResponse(
        itemModel: AdminAuditEntryResponse::class,
        description: 'Trilha de auditoria paginada.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[ValidationErrorResponse(
        examples: [
            'INVALID_CHOICE' => new ApiErrorExample(
                message: 'A ação informada é inválida.',
                field: 'action',
                summary: 'Filtros inválidos',
                description: 'A ação, o tipo de alvo, o autor ou o período são inválidos.',
            ),
        ],
        fields: [
            'page' => 'Página, a partir de 1.',
            'perPage' => 'Itens por página, no máximo 30.',
            'action' => 'Ação administrativa.',
            'targetType' => 'Tipo do alvo.',
            'actorId' => 'Autor da ação.',
            'from' => 'Data/hora mínima.',
            'to' => 'Data/hora máxima.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function list(#[MapQueryString] AdminAuditListQuery $query): JsonResponse
    {
        $this->rateLimitGuard->assertWithin($this->readLimiter, $this->actorId());

        $result = ($this->listAdminAuditHandler)(new ListAdminAuditQuery(new AdminAuditSearchCriteria(
            page: $query->page,
            perPage: $query->perPage,
            action: null === $query->action ? null : AdminAuditAction::from($query->action),
            targetType: null === $query->targetType ? null : AdminAuditTargetType::from($query->targetType),
            actorId: $query->actorId,
            from: $this->instantOrNull($query->from, 'from'),
            to: $this->instantOrNull($query->to, 'to'),
        )));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        /** @var AdminAuditPage $page */
        $page = $result->value();

        return ApiResponse::ok(array_map(
            static fn (AdminAuditEntry $entry): AdminAuditEntryResponse => AdminAuditEntryResponse::fromEntity($entry),
            $page->items,
        ))
            ->withPagination($query->page, $query->perPage, $page->total)
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

    private function actorId(): string
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new LogicException('Usuário autenticado ausente.');
        }

        return $user->getUserIdentifier();
    }
}
