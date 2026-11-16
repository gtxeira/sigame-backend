<?php

declare(strict_types=1);

namespace App\Shared\Application\Administration;

/**
 * Porta de escrita da trilha de auditoria global.
 *
 * O adapter concreto vive no módulo `Administration`. Os casos de uso de
 * Identity, Groups e Activities chamam esta porta **dentro da própria
 * transação**: se a auditoria falhar, a ação é desfeita (nunca existe ação
 * administrativa sem registro).
 */
interface AdminAuditRecorderInterface
{
    public function record(AdminAuditRecord $record): void;
}
