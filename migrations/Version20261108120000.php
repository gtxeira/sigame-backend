<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Troca a URL livre da foto pela chave do objeto no storage.
 *
 * Os valores antigos eram URLs de desenvolvimento e não servem como chave;
 * por isso são limpos antes de reduzir o tamanho da coluna.
 */
final class Version20261108120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomeia users.photo_url para photo_key e limpa valores antigos.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users RENAME COLUMN photo_url TO photo_key');
        $this->addSql('UPDATE users SET photo_key = NULL');
        $this->addSql('ALTER TABLE users ALTER COLUMN photo_key TYPE VARCHAR(255)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN photo_key TYPE VARCHAR(2048)');
        $this->addSql('ALTER TABLE users RENAME COLUMN photo_key TO photo_url');
    }
}
