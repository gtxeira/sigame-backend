<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Passa a exigir e-mail verificado para autenticar.
 *
 * Contas criadas antes desta regra são marcadas como verificadas para não
 * perderem o acesso; a exigência vale para cadastros novos.
 */
final class Version20261115120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Marca contas existentes como verificadas antes de exigir confirmação no login.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE users
            SET email_verified_at = created_at
            WHERE email_verified_at IS NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Não há como distinguir as contas marcadas em up() das verificadas de
        // fato, então a reversão é intencionalmente vazia.
    }
}
