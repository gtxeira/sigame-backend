<?php

namespace App\Controller;

use App\Helpers\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/health', name: 'app_health')]
    public function index(): JsonResponse
    {
        return ApiResponse::ok('ok')->toJsonResponse();
    }
}
