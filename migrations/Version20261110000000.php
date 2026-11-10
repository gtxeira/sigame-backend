<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cria o progresso de gamificação, o ledger de XP e o backfill inicial.
 */
final class Version20261110000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria user_progress e xp_events com backfill a partir de users.level.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_progress (user_id UUID NOT NULL, xp_balance INT DEFAULT 0 NOT NULL, xp_lifetime INT DEFAULT 0 NOT NULL, level SMALLINT DEFAULT 1 NOT NULL, current_weekly_streak INT DEFAULT 0 NOT NULL, best_weekly_streak INT DEFAULT 0 NOT NULL, last_completed_week VARCHAR(10) DEFAULT NULL, last_validated_activity_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, last_decay_period_end VARCHAR(10) DEFAULT NULL, decayed_periods INT DEFAULT 0 NOT NULL, rule_version VARCHAR(32) NOT NULL, created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, PRIMARY KEY (user_id))');
        $this->addSql('CREATE INDEX idx_user_progress_decay ON user_progress (last_validated_activity_at)');
        $this->addSql('CREATE TABLE xp_events (id UUID NOT NULL, user_id UUID NOT NULL, kind VARCHAR(40) NOT NULL, amount INT NOT NULL, source_type VARCHAR(40) DEFAULT NULL, source_id VARCHAR(64) DEFAULT NULL, activity_id VARCHAR(64) DEFAULT NULL, activity_date VARCHAR(10) DEFAULT NULL, week_key VARCHAR(10) DEFAULT NULL, rule_version VARCHAR(32) NOT NULL, metadata JSON DEFAULT NULL, idempotency_key VARCHAR(191) NOT NULL, reversed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, reversal_of_id UUID DEFAULT NULL, created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_xp_events_user_created ON xp_events (user_id, created_at)');
        $this->addSql('CREATE INDEX idx_xp_events_user_activity_date ON xp_events (user_id, activity_date)');
        $this->addSql('CREATE INDEX idx_xp_events_activity ON xp_events (activity_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_xp_events_idempotency ON xp_events (idempotency_key)');

        $this->addSql("INSERT INTO user_progress (user_id, xp_balance, xp_lifetime, level, rule_version, created_at, updated_at) SELECT id, 50 * level * (level - 1), 50 * level * (level - 1), level, '2026-11-v1', NOW(), NOW() FROM users");
        $this->addSql("INSERT INTO xp_events (id, user_id, kind, amount, source_type, source_id, rule_version, idempotency_key, created_at) SELECT gen_random_uuid(), id, 'backfill', 50 * level * (level - 1), 'user', id::text, '2026-11-v1', 'backfill:' || id::text, NOW() FROM users WHERE level > 1");
        $this->addSql("UPDATE user_progress p SET last_validated_activity_at = sub.max_validated FROM (SELECT aa.user_id, MAX(aa.validated_at) AS max_validated FROM activity_attendances aa WHERE aa.status = 'validated' AND aa.validated_at IS NOT NULL GROUP BY aa.user_id) sub WHERE p.user_id = sub.user_id");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE xp_events');
        $this->addSql('DROP TABLE user_progress');
    }
}
