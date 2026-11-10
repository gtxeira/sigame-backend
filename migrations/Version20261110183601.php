<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adiciona o limite opcional de membros aprovados por grupo.
 */
final class Version20261110183601 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona groups.max_members com null representando grupo ilimitado.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE groups ADD max_members INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE groups DROP max_members');
    }
}
