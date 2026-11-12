<?php

declare(strict_types=1);

namespace App\Shared\Application\User;

/**
 * Porta de leitura dos dados públicos de participantes.
 *
 * Usada pelas listagens de grupo (membros e solicitações) para montar nome,
 * nível e foto sem importar o módulo Identity.
 */
interface UserMemberProfileReaderInterface
{
    /**
     * @param list<string> $userIds
     *
     * @return array<string, UserMemberProfile> indexado pelo id do usuário
     */
    public function profiles(array $userIds): array;
}
