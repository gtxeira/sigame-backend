<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\ValueObject\UserRole;
use App\Shared\Domain\Error\DomainError;
use App\Shared\Presentation\Http\Response\ApiResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bloqueia o uso da plataforma por conta com e-mail ainda não verificado.
 *
 * O login já exige a verificação e não emite tokens para conta pendente. Este
 * listener é a defesa em profundidade para qualquer token que exista fora desse
 * fluxo (legado, importação ou mudança futura de e-mail): enquanto o endereço
 * não for confirmado, a conta só pode ler o próprio perfil e usar o fluxo de
 * autenticação (login, refresh, logout, verificação e senha).
 *
 * Contas administrativas são isentas para não travar a operação, seguindo o
 * mesmo critério do `BirthDateGateListener`.
 *
 * O listener roda depois do firewall e depois do gate de data de nascimento
 * (priority menor): rotas já autenticadas apenas; falhas de autenticação
 * continuam sendo respondidas pelo entry point da API.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: -1)]
final readonly class EmailVerifiedGateListener
{
    /**
     * Rotas que precisam continuar funcionando para a conta confirmar o e-mail.
     *
     * @var list<string>
     */
    private const array ALLOWED_ROUTES = [
        'app_me',
    ];

    public function __construct(
        private Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $event->hasResponse()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Só a API autenticada de uso da plataforma. O fluxo de autenticação é
        // público e precisa continuar disponível para renovar ou encerrar sessão.
        if (!str_starts_with($path, '/api/v1/') || str_starts_with($path, '/api/v1/auth/')) {
            return;
        }

        $route = $request->attributes->get('_route');

        if (!is_string($route) || in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        $securityUser = $this->security->getUser();

        // Rota sem usuário autenticado: o firewall/entry point responde 401.
        if (!$securityUser instanceof SecurityUser) {
            return;
        }

        $user = $securityUser->user();

        if (UserRole::ADMIN_SIGAME === $user->role()) {
            return;
        }

        if (!$user->isEmailVerified()) {
            $this->deny($event, IdentityErrors::emailNotVerified());
        }
    }

    private function deny(RequestEvent $event, DomainError $error): void
    {
        $item = [
            'code' => $error->code,
            'message' => $error->message,
            ...(null === $error->field ? [] : ['field' => $error->field]),
        ];

        $event->setResponse(
            ApiResponse::error([$item])->toJsonResponse(Response::HTTP_FORBIDDEN),
        );
    }
}
