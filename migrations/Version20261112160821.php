<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ciclo de vida do grupo: ativação persistida, arquivamento e exclusão lógica.
 *
 * - `activated_at`: espelho da ativação (3 aprovados), usado pelo gate de
 *   formação e pelo arquivamento automático.
 * - `archived_*`: arquivamento reversível, com autor e motivo opcionais.
 * - `deleted_*`: exclusão lógica terminal, preservando o histórico.
 * - Índice parcial para localizar grupos em formação sem atividade.
 * - Índice único parcial de dedupe por criador, nome e modalidade.
 */
final class Version20261112160821 extends AbstractMigration
{
    private const string DEDUPE_INDEX = 'uniq_groups_creator_name_modality';
    private const string IDLE_INDEX = 'idx_groups_forming_idle';

    public function getDescription(): string
    {
        return 'Adiciona ciclo de vida (ativacao, arquivamento e exclusao logica) aos grupos.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE groups ADD activated_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE groups ADD archived_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE groups ADD archived_by UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE groups ADD archive_reason VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE groups ADD deleted_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE groups ADD deleted_by UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE groups ADD delete_reason VARCHAR(500) DEFAULT NULL');

        // Backfill: grupos que já possuem 3 ou mais aprovados nascem ativos.
        $this->addSql(<<<'SQL'
            UPDATE groups g
            SET activated_at = g.updated_at
            WHERE g.activated_at IS NULL
              AND (
                  SELECT COUNT(*)
                  FROM group_memberships m
                  WHERE m.group_id = g.id
                    AND m.status = 'approved'
              ) >= 3
            SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX idx_groups_forming_idle
                ON groups (updated_at)
                WHERE activated_at IS NULL
                  AND archived_at IS NULL
                  AND deleted_at IS NULL
            SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_groups_creator_name_modality
                ON groups (created_by, lower(name), modality)
                WHERE deleted_at IS NULL
                  AND archived_at IS NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX '.self::DEDUPE_INDEX);
        $this->addSql('DROP INDEX '.self::IDLE_INDEX);
        $this->addSql('ALTER TABLE groups DROP activated_at');
        $this->addSql('ALTER TABLE groups DROP archived_at');
        $this->addSql('ALTER TABLE groups DROP archived_by');
        $this->addSql('ALTER TABLE groups DROP archive_reason');
        $this->addSql('ALTER TABLE groups DROP deleted_at');
        $this->addSql('ALTER TABLE groups DROP deleted_by');
        $this->addSql('ALTER TABLE groups DROP delete_reason');
    }
}
