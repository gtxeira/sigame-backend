<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Impõe a unicidade global de users.email.
 *
 * A migration aborta se houver e-mails duplicados ou usuários sem e-mail,
 * porque a correção exige decisão manual sobre qual conta permanece com o
 * endereço.
 */
final class Version20261106090100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Valida e cria o índice único de users.email.';
    }

    public function up(Schema $schema): void
    {
        $withoutEmail = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE email IS NULL');

        if ($withoutEmail > 0) {
            throw new RuntimeException(sprintf(
                'Não foi possível definir users.email para %d usuário(s) sem identidade com e-mail. Corrija os dados antes de migrar.',
                $withoutEmail,
            ));
        }

        /** @var list<array{email: string, total: int|string}> $duplicates */
        $duplicates = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT lower(email) AS email, COUNT(*) AS total
            FROM users
            GROUP BY lower(email)
            HAVING COUNT(*) > 1
            ORDER BY lower(email)
            SQL);

        if ([] !== $duplicates) {
            $report = implode(', ', array_map(
                static fn (array $row): string => $row['email'].' ('.$row['total'].' contas)',
                $duplicates,
            ));

            throw new RuntimeException(sprintf(
                'E-mails duplicados impedem a criação do índice único de users.email: %s. Resolva manualmente antes de migrar.',
                $report,
            ));
        }

        $this->addSql('UPDATE users SET email = lower(trim(email))');
        $this->addSql('ALTER TABLE users ALTER email SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_users_email');
        $this->addSql('ALTER TABLE users ALTER email DROP NOT NULL');
    }
}
