<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adiciona o e-mail canônico em users e preenche os dados existentes.
 *
 * A imposição de unicidade fica na migration seguinte, que valida duplicatas
 * depois que o backfill já foi aplicado.
 */
final class Version20261106090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona users.email e users.email_verified_at e faz o backfill a partir das identidades.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD email VARCHAR(255)');
        $this->addSql('ALTER TABLE users ADD email_verified_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE users u
            SET email = COALESCE(
                (SELECT i.email FROM user_identities i
                 WHERE i.user_id = u.id AND i.provider = 'local'
                 LIMIT 1),
                (SELECT i.email FROM user_identities i
                 WHERE i.user_id = u.id AND i.email IS NOT NULL
                 ORDER BY i.created_at
                 LIMIT 1)
            )
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE users u
            SET email_verified_at = (
                SELECT MAX(i.email_verified_at)
                FROM user_identities i
                WHERE i.user_id = u.id
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP email_verified_at');
        $this->addSql('ALTER TABLE users DROP email');
    }
}
