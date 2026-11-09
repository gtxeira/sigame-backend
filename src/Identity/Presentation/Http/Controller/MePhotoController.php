<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\Storage\ProfilePhotoStorageInterface;
use App\Identity\Application\UseCase\RemoveProfilePhoto\RemoveProfilePhotoHandler;
use App\Identity\Application\UseCase\UploadProfilePhoto\UploadProfilePhotoCommand;
use App\Identity\Application\UseCase\UploadProfilePhoto\UploadProfilePhotoHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Response\CurrentUserResponse;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ResultResponder;
use App\Shared\Presentation\Http\Validation\RequestViolationFactory;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ciclo de vida da foto de perfil do usuário autenticado.
 *
 * O upload usa multipart/form-data e substitui a foto anterior; a remoção é
 * idempotente, de modo que repetir o DELETE devolve o mesmo `204`.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Minha conta')]
final class MePhotoController extends AbstractController
{
    public function __construct(
        private readonly UploadProfilePhotoHandler $uploadProfilePhotoHandler,
        private readonly RemoveProfilePhotoHandler $removeProfilePhotoHandler,
        private readonly ProfilePhotoStorageInterface $photoStorage,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.user_photo')]
        private readonly RateLimiterFactoryInterface $photoLimiter,
    ) {
    }

    /**
     * Alterar a foto de perfil
     *
     * Envia uma nova foto em `multipart/form-data`, no campo `photo`. A foto
     * anterior é substituída quando existe.
     */
    #[Route('/users/me/photo', name: 'app_me_photo_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['photo'],
                properties: [
                    new OA\Property(property: 'photo', description: 'Imagem JPEG, PNG ou WebP.', type: 'string', format: 'binary'),
                ],
            ),
        ),
    )]
    #[SuccessResponse(dataModel: CurrentUserResponse::class, description: 'Foto de perfil atualizada.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['photo' => 'A foto de perfil deve ser uma imagem JPEG, PNG ou WebP.'],
                summary: 'Foto inválida',
                description: 'O arquivo não foi enviado, excede o limite ou não é uma imagem aceita.',
            ),
        ],
        fields: ['photo' => 'Arquivo da foto de perfil.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[InternalErrorResponse]
    public function update(
        #[MapUploadedFile(
            name: 'photo',
            constraints: [
                new Assert\Image(
                    maxSize: '2M',
                    mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                    minWidth: 200,
                    minHeight: 200,
                    maxWidth: 4096,
                    maxHeight: 4096,
                    maxSizeMessage: 'A foto de perfil deve ter no máximo 2 MB.',
                    mimeTypesMessage: 'A foto de perfil deve ser uma imagem JPEG, PNG ou WebP.',
                    minWidthMessage: 'A foto de perfil deve ter no mínimo 200 pixels de largura.',
                    minHeightMessage: 'A foto de perfil deve ter no mínimo 200 pixels de altura.',
                    maxWidthMessage: 'A foto de perfil deve ter no máximo 4096 pixels de largura.',
                    maxHeightMessage: 'A foto de perfil deve ter no máximo 4096 pixels de altura.',
                    sizeNotDetectedMessage: 'Não foi possível ler as dimensões da foto de perfil.',
                ),
            ],
        )]
        ?UploadedFile $photo = null,
    ): JsonResponse {
        if (null === $photo) {
            throw RequestViolationFactory::unprocessable('photo', 'A foto de perfil é obrigatória.');
        }

        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        $user = $securityUser->user();
        $this->rateLimitGuard->assertWithin($this->photoLimiter, $user->id()->value());

        $result = ($this->uploadProfilePhotoHandler)(new UploadProfilePhotoCommand(
            userId: $user->id(),
            sourcePath: $photo->getPathname(),
            mimeType: $photo->getMimeType() ?? 'application/octet-stream',
        ));

        return $this->resultResponder->success(
            $result->map(fn (User $updated): CurrentUserResponse => CurrentUserResponse::fromEntity(
                user: $updated,
                photoUrl: $this->photoStorage->url($updated->photoKey()),
            )),
        );
    }

    /**
     * Remover a foto de perfil
     *
     * Remove a foto atual. A operação é idempotente: sem foto, devolve `204`
     * sem alterar o usuário.
     */
    #[Route('/users/me/photo', name: 'app_me_photo_remove', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Foto de perfil removida.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function remove(): Response
    {
        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        $user = $securityUser->user();
        $this->rateLimitGuard->assertWithin($this->photoLimiter, $user->id()->value());

        ($this->removeProfilePhotoHandler)($user->id());

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
