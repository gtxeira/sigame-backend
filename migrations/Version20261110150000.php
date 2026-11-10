<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajusta a coluna de papel para acomodar ADMIN_SIGAME.
 */
final class Version20261110150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Amplia users.role para 20 caracteres para suportar ADMIN_SIGAME.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(20)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(10)');
    }
}
