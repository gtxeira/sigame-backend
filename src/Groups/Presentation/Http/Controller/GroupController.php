<?php

declare(strict_types=1);

namespace App\Groups\Presentation\Http\Controller;

use App\Groups\Application\UseCase\CreateGroup\CreateGroupCommand;
use App\Groups\Application\UseCase\CreateGroup\CreateGroupHandler;
use App\Groups\Application\UseCase\GetGroup\GetGroupHandler;
use App\Groups\Application\UseCase\GetGroup\GetGroupQuery;
use App\Groups\Application\UseCase\ListGroups\ListGroupsHandler;
use App\Groups\Application\UseCase\ListGroups\ListGroupsQuery;
use App\Groups\Application\UseCase\UpdateGroup\UpdateGroupCommand;
use App\Groups\Application\UseCase\UpdateGroup\UpdateGroupHandler;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Repository\GroupPage;
use App\Groups\Domain\Group\Repository\GroupSearchCriteria;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Presentation\Http\Request\CreateGroupRequest;
use App\Groups\Presentation\Http\Request\GroupListQuery;
use App\Groups\Presentation\Http\Request\UpdateGroupRequest;
use App\Groups\Presentation\Http\Response\GroupResponse;
use App\Groups\Presentation\Http\Response\GroupSummaryResponse;
use App\Shared\Domain\ValueObject\Modality;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\NotFoundResponse;
use App\Shared\Presentation\Http\OpenApi\PaginatedSuccessResponse;
use App\Shared\Presentation\Http\OpenApi\ResourceConflictResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\UnsupportedMediaTypeResponse;
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
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Criação, consulta e atualização de grupos esportivos.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Grupos')]
final class GroupController extends AbstractController
{
    public function __construct(
        private readonly CreateGroupHandler $createGroupHandler,
        private readonly UpdateGroupHandler $updateGroupHandler,
        private readonly GetGroupHandler $getGroupHandler,
        private readonly ListGroupsHandler $listGroupsHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.group_create')]
        private readonly RateLimiterFactoryInterface $createLimiter,
        #[Autowire(service: 'limiter.group_manage')]
        private readonly RateLimiterFactoryInterface $manageLimiter,
    ) {
    }

    /**
     * Criar grupo
     *
     * Cria um grupo esportivo e registra o usuário autenticado como primeiro
     * administrador. Exige nível 2 ou superior e respeita o limite de três
     * grupos por usuário.
     */
    #[Route('/groups', name: 'app_group_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(
        dataModel: GroupResponse::class,
        response: Response::HTTP_CREATED,
        description: 'Grupo criado com sucesso.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse(
        examples: [
            'GROUP_LEVEL_INSUFFICIENT' => new ApiErrorExample(
                message: 'É necessário estar no nível 2 ou superior para criar um grupo.',
                summary: 'Nível insuficiente',
                description: 'O usuário ainda não concluiu o onboarding ou não alcançou o nível mínimo.',
            ),
        ],
    )]
    #[ResourceConflictResponse(
        examples: [
            'GROUP_LIMIT_REACHED' => new ApiErrorExample(
                message: 'O usuário já participa do número máximo de 3 grupos.',
                summary: 'Limite de grupos atingido',
                description: 'O usuário precisa sair de um grupo antes de criar outro.',
            ),
        ],
    )]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'O nome do grupo é obrigatório.',
                field: 'name',
                summary: 'Erros de validação',
                description: 'Nome, modalidade ou link estão ausentes ou em formato inválido.',
            ),
        ],
        fields: [
            'name' => 'Nome do grupo.',
            'description' => 'Descrição do grupo.',
            'modality' => 'Modalidade do grupo.',
            'communicationLink' => 'Link externo de comunicação.',
            'maxMembers' => 'Limite máximo de membros aprovados.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function create(#[MapRequestPayload] CreateGroupRequest $request): JsonResponse
    {
        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->createLimiter, $actor->value());

        $result = ($this->createGroupHandler)(new CreateGroupCommand(
            actorId: $actor,
            name: $request->name,
            description: $request->description,
            modality: Modality::from($request->modality),
            communicationLink: $request->communicationLink,
            maxMembers: $request->maxMembers,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(fn (Group $group): GroupResponse => GroupResponse::fromEntity($group, $actor)),
            Response::HTTP_CREATED,
        );
    }

    /**
     * Listar grupos
     *
     * Lista os grupos visíveis para usuários autenticados, com filtros por
     * modalidade, estado e nome. O link de comunicação não é exposto nesta
     * listagem.
     */
    #[Route('/groups', name: 'app_group_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[PaginatedSuccessResponse(
        itemModel: GroupSummaryResponse::class,
        description: 'Lista paginada de grupos.',
    )]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[ValidationErrorResponse(
        examples: [
            'INVALID_CHOICE' => new ApiErrorExample(
                message: 'O estado informado é inválido.',
                field: 'status',
                summary: 'Filtros inválidos',
                description: 'A modalidade, o estado ou a paginação são inválidos.',
            ),
        ],
        fields: [
            'page' => 'Página solicitada.',
            'perPage' => 'Itens por página.',
            'modality' => 'Modalidade do grupo.',
            'status' => 'Estado do grupo.',
            'name' => 'Busca parcial pelo nome.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function list(#[MapQueryString] GroupListQuery $query): JsonResponse
    {
        $actor = $this->actorId();

        $criteria = new GroupSearchCriteria(
            page: $query->page,
            perPage: $query->perPage,
            modality: null === $query->modality ? null : Modality::from($query->modality),
            status: null === $query->status ? null : GroupStatus::from($query->status),
            name: $query->name,
            availableOnly: $query->availableOnly,
        );

        $result = ($this->listGroupsHandler)(new ListGroupsQuery($criteria));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        /** @var GroupPage $page */
        $page = $result->value();

        $items = array_map(
            static fn (Group $group): GroupSummaryResponse => GroupSummaryResponse::fromEntity($group, $actor),
            $page->items,
        );

        return ApiResponse::ok($items)
            ->withPagination($criteria->page, $criteria->perPage, $page->total)
            ->toJsonResponse();
    }

    /**
     * Obter grupo
     *
     * Retorna os dados do grupo. O link de comunicação só é exibido para
     * membros aprovados.
     */
    #[Route('/groups/{id}', name: 'app_group_show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: GroupResponse::class, description: 'Dados do grupo.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[NotFoundResponse]
    #[InternalErrorResponse]
    public function show(string $id): JsonResponse
    {
        $actor = $this->actorId();
        $result = ($this->getGroupHandler)(new GetGroupQuery(
            groupId: GroupId::fromString($id),
            viewerId: $actor,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(fn (Group $group): GroupResponse => GroupResponse::fromEntity($group, $actor)),
        );
    }

    /**
     * Atualizar grupo
     *
     * Altera somente os campos presentes no corpo. `description` e
     * `communicationLink` aceitam `null` para limpar. Apenas administradores
     * podem atualizar o grupo.
     */
    #[Route('/groups/{id}', name: 'app_group_update', methods: ['PATCH'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[SuccessResponse(dataModel: GroupResponse::class, description: 'Grupo atualizado com sucesso.')]
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
    #[ResourceConflictResponse(
        examples: [
            'GROUP_MEMBER_LIMIT_BELOW_APPROVED' => new ApiErrorExample(
                message: 'O limite de 3 membros é menor que os 4 membros já aprovados no grupo.',
                summary: 'Limite abaixo dos membros aprovados',
                description: 'O limite não pode ser menor que a quantidade atual de membros aprovados.',
            ),
        ],
    )]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'Informe ao menos um campo para atualização.',
                field: 'body',
                summary: 'Erros de validação',
                description: 'O corpo está vazio ou um campo tem formato inválido.',
            ),
            'INVALID_FORMAT' => new ApiErrorExample(
                message: 'O link de comunicação deve ser uma URL http ou https válida.',
                field: 'communicationLink',
                summary: 'Link inválido',
                description: 'O link não é uma URL http/https válida.',
            ),
        ],
        fields: [
            'body' => 'Corpo da requisição.',
            'name' => 'Nome do grupo.',
            'description' => 'Descrição do grupo.',
            'modality' => 'Modalidade do grupo.',
            'communicationLink' => 'Link externo de comunicação.',
            'maxMembers' => 'Limite máximo de membros aprovados.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function update(
        string $id,
        #[MapRequestPayload(mapWhenEmpty: true)] UpdateGroupRequest $request,
        HttpRequest $httpRequest,
    ): JsonResponse {
        $payload = $httpRequest->getPayload();
        $hasName = $payload->has('name');
        $hasDescription = $payload->has('description');
        $hasModality = $payload->has('modality');
        $hasLink = $payload->has('communicationLink');
        $hasMaxMembers = $payload->has('maxMembers');

        if (!$hasName && !$hasDescription && !$hasModality && !$hasLink && !$hasMaxMembers) {
            throw RequestViolationFactory::unprocessable('FIELD_REQUIRED', 'body', 'Informe ao menos um campo para atualização.');
        }

        if ($hasName && null === $request->name) {
            throw RequestViolationFactory::unprocessable('FIELD_REQUIRED', 'name', 'O nome não pode ser nulo.');
        }

        if ($hasModality && null === $request->modality) {
            throw RequestViolationFactory::unprocessable('INVALID_CHOICE', 'modality', 'A modalidade não pode ser nula.');
        }

        $actor = $this->actorId();
        $this->rateLimitGuard->assertWithin($this->manageLimiter, $actor->value());

        $result = ($this->updateGroupHandler)(new UpdateGroupCommand(
            groupId: GroupId::fromString($id),
            actorId: $actor,
            name: $hasName ? $request->name : null,
            description: $request->description,
            descriptionProvided: $hasDescription,
            modality: $hasModality ? $request->modality : null,
            communicationLink: $request->communicationLink,
            communicationLinkProvided: $hasLink,
            maxMembers: $request->maxMembers,
            maxMembersProvided: $hasMaxMembers,
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
