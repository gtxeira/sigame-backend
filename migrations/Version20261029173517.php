<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029173517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserva microssegundos e timezone em users.created_at e users.updated_at.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ALTER created_at TYPE TIMESTAMP(6) WITH TIME ZONE USING created_at AT TIME ZONE 'UTC'");
        $this->addSql("ALTER TABLE users ALTER updated_at TYPE TIMESTAMP(6) WITH TIME ZONE USING updated_at AT TIME ZONE 'UTC'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING created_at AT TIME ZONE 'UTC'");
        $this->addSql("ALTER TABLE users ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING updated_at AT TIME ZONE 'UTC'");
    }
}
