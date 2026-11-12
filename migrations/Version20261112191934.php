<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Responsável (owner) transferível nos grupos.
 *
 * - `groups.owner_id`: responsável atual, iniciado como o criador.
 * - Índice único parcial de dedupe por responsável, nome e modalidade.
 * - Histórico append-only das transferências de responsabilidade.
 */
final class Version20261112191934 extends AbstractMigration
{
    private const string OWNER_DEDUPE_INDEX = 'uniq_groups_owner_name_modality';
    private const string CREATOR_DEDUPE_INDEX = 'uniq_groups_creator_name_modality';

    public function getDescription(): string
    {
        return 'Adiciona responsavel (owner) transferivel aos grupos.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE groups ADD owner_id UUID DEFAULT NULL');

        // Backfill: todo grupo existente tem o criador como responsável.
        $this->addSql('UPDATE groups SET owner_id = created_by WHERE owner_id IS NULL');
        $this->addSql('ALTER TABLE groups ALTER owner_id SET NOT NULL');

        $this->addSql('DROP INDEX '.self::CREATOR_DEDUPE_INDEX);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_groups_owner_name_modality
                ON groups (owner_id, lower(name), modality)
                WHERE deleted_at IS NULL
                  AND archived_at IS NULL
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE group_ownership_transfers (
                id UUID NOT NULL,
                previous_owner_id UUID NOT NULL,
                new_owner_id UUID NOT NULL,
                actor_id UUID NOT NULL,
                transferred_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                group_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $this->addSql('CREATE INDEX idx_group_ownership_transfers_group ON group_ownership_transfers (group_id, transferred_at)');
        $this->addSql('CREATE INDEX IDX_7A673ED3FE54D947 ON group_ownership_transfers (group_id)');
        $this->addSql('ALTER TABLE group_ownership_transfers ADD CONSTRAINT FK_7A673ED3FE54D947 FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE group_ownership_transfers DROP CONSTRAINT FK_7A673ED3FE54D947');
        $this->addSql('DROP TABLE group_ownership_transfers');

        $this->addSql('DROP INDEX '.self::OWNER_DEDUPE_INDEX);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_groups_creator_name_modality
                ON groups (created_by, lower(name), modality)
                WHERE deleted_at IS NULL
                  AND archived_at IS NULL
            SQL);

        $this->addSql('ALTER TABLE groups DROP owner_id');
    }
}
