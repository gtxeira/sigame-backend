<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Substitui os tokens de link por códigos OTP e tickets temporários de reset.
 */
final class Version20261114190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria desafios OTP e tickets de reset; remove identity_tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_otp_challenges (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                purpose VARCHAR(30) NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                attempts SMALLINT DEFAULT 0 NOT NULL,
                expires_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                consumed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_identity_otp_challenges_user_purpose ON identity_otp_challenges (user_id, purpose)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_identity_otp_challenges_active
                ON identity_otp_challenges (user_id, purpose)
                WHERE consumed_at IS NULL
            SQL);
        $this->addSql('ALTER TABLE identity_otp_challenges ADD CONSTRAINT fk_identity_otp_challenges_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE identity_password_reset_tickets (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                challenge_id UUID NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                consumed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_identity_password_reset_tickets_token_hash ON identity_password_reset_tickets (token_hash)');
        $this->addSql('CREATE INDEX idx_identity_password_reset_tickets_user ON identity_password_reset_tickets (user_id)');
        $this->addSql('ALTER TABLE identity_password_reset_tickets ADD CONSTRAINT fk_identity_password_reset_tickets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE identity_password_reset_tickets ADD CONSTRAINT fk_identity_password_reset_tickets_challenge FOREIGN KEY (challenge_id) REFERENCES identity_otp_challenges (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('DROP TABLE identity_tokens');
    }

    public function down(Schema $schema): void
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

        $this->addSql('ALTER TABLE identity_password_reset_tickets DROP CONSTRAINT fk_identity_password_reset_tickets_challenge');
        $this->addSql('ALTER TABLE identity_password_reset_tickets DROP CONSTRAINT fk_identity_password_reset_tickets_user');
        $this->addSql('DROP TABLE identity_password_reset_tickets');
        $this->addSql('ALTER TABLE identity_otp_challenges DROP CONSTRAINT fk_identity_otp_challenges_user');
        $this->addSql('DROP TABLE identity_otp_challenges');
    }
}
