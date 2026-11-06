<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Página de diagnóstico do fluxo OAuth em desenvolvimento.
 *
 * Só é registrada em `dev`; em produção a rota não existe.
 */
final class DevOAuthController extends AbstractController
{
    #[Route('/dev/google-oauth', name: 'app_dev_google_oauth', methods: ['GET'], env: 'dev')]
    public function __invoke(): Response
    {
        return $this->render('dev/google_oauth.html.twig');
    }
}
