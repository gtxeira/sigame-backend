<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Extrai credenciais de users para user_identities, torna CPF opcional e
 * cria a tabela de refresh tokens opacos.
 */
final class Version20261104120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria user_identities e refresh_tokens; move email/senha para identidades e torna CPF opcional.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_identities (
                id UUID NOT NULL,
                provider VARCHAR(20) NOT NULL,
                provider_subject VARCHAR(255) NOT NULL,
                email VARCHAR(255) DEFAULT NULL,
                password VARCHAR(255) DEFAULT NULL,
                email_verified_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                user_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_user_identities_email ON user_identities (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_identities_provider_subject ON user_identities (provider, provider_subject)');
        $this->addSql('CREATE INDEX IDX_5D5EEA04A76ED395 ON user_identities (user_id)');
        $this->addSql('ALTER TABLE user_identities ADD CONSTRAINT FK_5D5EEA04A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');

        // Backfill: todo usuário existente vira uma identidade local.
        $this->addSql(<<<'SQL'
            INSERT INTO user_identities (id, provider, provider_subject, email, password, email_verified_at, created_at, user_id)
            SELECT gen_random_uuid(), 'local', lower(email), lower(email), password, NULL, created_at, id
            FROM users
            SQL);

        $this->addSql('DROP INDEX uniq_1483a5e9e7927c74');
        $this->addSql('DROP INDEX uniq_1483a5e93e3e11f0');
        $this->addSql('ALTER TABLE users DROP email');
        $this->addSql('ALTER TABLE users DROP password');
        $this->addSql('ALTER TABLE users ALTER cpf DROP NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_cpf ON users (cpf) WHERE cpf IS NOT NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_tokens (
                id UUID NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                family_id UUID NOT NULL,
                expires_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                revoked_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                user_id UUID NOT NULL,
                replaced_by_id UUID DEFAULT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_refresh_tokens_user ON refresh_tokens (user_id)');
        $this->addSql('CREATE INDEX idx_refresh_tokens_family ON refresh_tokens (family_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_refresh_tokens_token_hash ON refresh_tokens (token_hash)');
        $this->addSql('CREATE INDEX IDX_9BACE7E19AC69B54 ON refresh_tokens (replaced_by_id)');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_9BACE7E1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_9BACE7E19AC69B54 FOREIGN KEY (replaced_by_id) REFERENCES refresh_tokens (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // Migração parcialmente irreversível: usuários sem CPF ou sem identidade
        // local impedem restaurar as colunas NOT NULL originais.
        $this->addSql('ALTER TABLE refresh_tokens DROP CONSTRAINT FK_9BACE7E19AC69B54');
        $this->addSql('ALTER TABLE refresh_tokens DROP CONSTRAINT FK_9BACE7E1A76ED395');
        $this->addSql('DROP TABLE refresh_tokens');

        $this->addSql('DROP INDEX uniq_users_cpf');
        $this->addSql('ALTER TABLE users ADD email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD password VARCHAR(255) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE users u
            SET email = i.email,
                password = i.password
            FROM user_identities i
            WHERE i.user_id = u.id AND i.provider = 'local'
            SQL);
        $this->addSql('ALTER TABLE users ALTER email SET NOT NULL');
        $this->addSql('ALTER TABLE users ALTER password SET NOT NULL');
        $this->addSql('ALTER TABLE users ALTER cpf SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E93E3E11F0 ON users (cpf)');

        $this->addSql('ALTER TABLE user_identities DROP CONSTRAINT FK_5D5EEA04A76ED395');
        $this->addSql('DROP TABLE user_identities');
    }
}
