<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Corrige os índices parciais de group_memberships para os valores reais dos
 * enums persistidos pelo Doctrine (minúsculos).
 */
final class Version20261109230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Corrige os índices parciais de group_memberships para os valores dos enums.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_group_memberships_supervisor');
        $this->addSql('DROP INDEX uniq_group_memberships_active');
        $this->addSql("CREATE UNIQUE INDEX uniq_group_memberships_supervisor ON group_memberships (group_id) WHERE (is_supervisor = TRUE AND status = 'approved')");
        $this->addSql("CREATE UNIQUE INDEX uniq_group_memberships_active ON group_memberships (group_id, user_id) WHERE (status IN ('pending', 'approved'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_group_memberships_supervisor');
        $this->addSql('DROP INDEX uniq_group_memberships_active');
        $this->addSql("CREATE UNIQUE INDEX uniq_group_memberships_supervisor ON group_memberships (group_id) WHERE (is_supervisor = TRUE AND status = 'APPROVED')");
        $this->addSql("CREATE UNIQUE INDEX uniq_group_memberships_active ON group_memberships (group_id, user_id) WHERE (status IN ('PENDING', 'APPROVED'))");
    }
}
