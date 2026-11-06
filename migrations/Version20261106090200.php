<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cria os tokens de uso único para verificação de e-mail e reset de senha.
 */
final class Version20261106090200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria identity_tokens com hash único, expiração e consumo.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_tokens (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                purpose VARCHAR(30) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                consumed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_identity_tokens_token_hash ON identity_tokens (token_hash)');
        $this->addSql('CREATE INDEX idx_identity_tokens_user_purpose ON identity_tokens (user_id, purpose)');
        $this->addSql('ALTER TABLE identity_tokens ADD CONSTRAINT fk_identity_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_tokens DROP CONSTRAINT fk_identity_tokens_user');
        $this->addSql('DROP TABLE identity_tokens');
    }
}
