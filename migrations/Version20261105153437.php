<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Torna users.updated_at obrigatório, backfillando os registros existentes com created_at.
 */
final class Version20261105153437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Torna users.updated_at NOT NULL e preenche os valores nulos com created_at.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE users SET updated_at = created_at WHERE updated_at IS NULL');
        $this->addSql('ALTER TABLE users ALTER updated_at SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER updated_at DROP NOT NULL');
    }
}
