<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cria o agregado de grupos e as participações dos membros.
 *
 * O criador é registrado em `groups.created_by`, independente do papel de
 * administrador. Não existe índice único de administrador: o grupo pode ter
 * vários admins, mas o domínio garante ao menos um.
 */
final class Version20261109120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria groups e group_memberships com limites e índices parciais.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE groups (
                id UUID NOT NULL,
                created_by UUID NOT NULL,
                name VARCHAR(80) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                modality VARCHAR(20) NOT NULL,
                communication_link VARCHAR(2048) DEFAULT NULL,
                created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);

        $this->addSql('CREATE INDEX idx_groups_created_by ON groups (created_by)');

        $this->addSql(<<<'SQL'
            CREATE TABLE group_memberships (
                id UUID NOT NULL,
                group_id UUID NOT NULL,
                user_id UUID NOT NULL,
                role VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL,
                is_supervisor BOOLEAN NOT NULL DEFAULT FALSE,
                requested_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                decided_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
                decided_by UUID DEFAULT NULL,
                removed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
                removed_by UUID DEFAULT NULL,
                removal_reason VARCHAR(500) DEFAULT NULL,
                created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);

        $this->addSql('CREATE INDEX idx_group_memberships_user_status ON group_memberships (user_id, status)');
        $this->addSql('CREATE INDEX idx_group_memberships_group_status ON group_memberships (group_id, status)');
        $this->addSql('CREATE INDEX idx_group_memberships_group_role_status ON group_memberships (group_id, role, status)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_group_memberships_active
                ON group_memberships (group_id, user_id)
                WHERE (status IN ('PENDING', 'APPROVED'))
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_group_memberships_supervisor
                ON group_memberships (group_id)
                WHERE (is_supervisor = TRUE AND status = 'APPROVED')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE group_memberships
                ADD CONSTRAINT fk_group_memberships_group
                FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE group_memberships');
        $this->addSql('DROP TABLE groups');
    }
}
