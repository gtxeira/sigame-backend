<?php

declare(strict_types=1);

namespace App\Identity\Application\DTO;

/**
 * Finalidade de um fluxo OAuth iniciado pelo backend.
 */
enum SocialFlowPurpose
{
    case Login;
    case Link;
}
