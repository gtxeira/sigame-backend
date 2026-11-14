<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\ValueObject\UserRole;
use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Policy\AgePolicy;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Presentation\Http\Response\ApiResponse;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bloqueia o uso da plataforma por conta sem data de nascimento.
 *
 * A data é write-once e pré-requisito de qualquer interação: enquanto ausente,
 * a conta só pode ler o próprio perfil, definir a data e usar o fluxo de
 * autenticação (login, refresh, logout, e-mail e vínculo social).
 *
 * Contas administrativas são isentas para não travar a operação. Contas com
 * data de menor de idade que ainda estejam ativas (legado/importação) também
 * são bloqueadas como defesa em profundidade: a definição da data já remove ou
 * suspende esses casos, e o `ActiveUserChecker` cobre os suspensos.
 *
 * O listener roda depois do firewall e só examina rotas já autenticadas; falhas
 * de autenticação continuam sendo respondidas pelo entry point da API.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final readonly class BirthDateGateListener
{
    /**
     * Rotas que precisam continuar funcionando para a conta completar o cadastro.
     *
     * @var list<string>
     */
    private const array ALLOWED_ROUTES = [
        'app_me',
        'app_me_birth_date',
        'app_me_identity_link',
    ];

    public function __construct(
        private Security $security,
        private ClockInterface $clock,
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

        $birthDate = $user->birthDate();

        if (null === $birthDate) {
            $this->deny($event, IdentityErrors::profileIncomplete());

            return;
        }

        if (!$birthDate->hasReached(AgePolicy::MINIMUM_AGE, Instant::fromDateTime($this->clock->now()))) {
            $this->deny($event, IdentityErrors::underMinimumAge(AgePolicy::MINIMUM_AGE));
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
