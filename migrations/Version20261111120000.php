<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adiciona a data de nascimento ao usuário.
 *
 * A coluna é nullable porque o cadastro não pede a data: ela é definida em
 * endpoint próprio (write-once) e é pré-requisito do onboarding.
 */
final class Version20261111120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona users.birth_date, obrigatória apenas para concluir o onboarding.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD birth_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP birth_date');
    }
}
