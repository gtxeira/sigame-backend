<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cria a trilha de auditoria append-only da administração global.
 *
 * Sem FK para o alvo: o registro precisa sobreviver à exclusão lógica do grupo,
 * da atividade ou da conta. O índice composto cobre a listagem por período.
 */
final class Version20261116014724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria admin_audit_entries para a trilha de auditoria da administração.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE admin_audit_entries (
                id UUID NOT NULL,
                action VARCHAR(40) NOT NULL,
                target_type VARCHAR(20) NOT NULL,
                target_id UUID NOT NULL,
                target_label VARCHAR(160) NOT NULL,
                actor_id UUID NOT NULL,
                actor_name VARCHAR(160) NOT NULL,
                reason VARCHAR(500) DEFAULT NULL,
                occurred_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_admin_audit_entries_occurred ON admin_audit_entries (occurred_at)');
        $this->addSql('CREATE INDEX idx_admin_audit_entries_target ON admin_audit_entries (target_type, target_id)');
        $this->addSql('CREATE INDEX idx_admin_audit_entries_actor ON admin_audit_entries (actor_id)');
        $this->addSql('CREATE INDEX idx_admin_audit_entries_action ON admin_audit_entries (action)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE admin_audit_entries');
    }
}
