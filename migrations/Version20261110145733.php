<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110145733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria notificações, entregas, histórico de suspensões e índices parciais de notificação.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification_deliveries (id UUID NOT NULL, channel VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, attempts INT DEFAULT 0 NOT NULL, last_error TEXT DEFAULT NULL, sent_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, notification_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_notification_deliveries_channel ON notification_deliveries (notification_id, channel)');
        $this->addSql('CREATE INDEX IDX_9475204AEF1A9D84 ON notification_deliveries (notification_id)');
        $this->addSql('CREATE TABLE notifications (id UUID NOT NULL, recipient_id UUID NOT NULL, type VARCHAR(60) NOT NULL, category VARCHAR(20) NOT NULL, title VARCHAR(120) NOT NULL, body VARCHAR(500) NOT NULL, payload JSON NOT NULL, deduplication_key VARCHAR(255) NOT NULL, read_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_notifications_recipient_created ON notifications (recipient_id, created_at)');
        $this->addSql('CREATE INDEX idx_notifications_recipient_unread ON notifications (recipient_id) WHERE (read_at IS NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifications_recipient_deduplication ON notifications (recipient_id, deduplication_key)');
        $this->addSql('CREATE TABLE user_suspensions (id UUID NOT NULL, action VARCHAR(20) NOT NULL, reason VARCHAR(500) DEFAULT NULL, occurred_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, user_id UUID NOT NULL, actor_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_user_suspensions_user_occurred ON user_suspensions (user_id, occurred_at)');
        $this->addSql('CREATE INDEX IDX_B297A99BA76ED395 ON user_suspensions (user_id)');
        $this->addSql('CREATE INDEX IDX_B297A99B10DAF24A ON user_suspensions (actor_id)');
        $this->addSql('ALTER TABLE notification_deliveries ADD CONSTRAINT FK_9475204AEF1A9D84 FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_suspensions ADD CONSTRAINT FK_B297A99BA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_suspensions ADD CONSTRAINT FK_B297A99B10DAF24A FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification_deliveries DROP CONSTRAINT FK_9475204AEF1A9D84');
        $this->addSql('ALTER TABLE user_suspensions DROP CONSTRAINT FK_B297A99BA76ED395');
        $this->addSql('ALTER TABLE user_suspensions DROP CONSTRAINT FK_B297A99B10DAF24A');
        $this->addSql('DROP TABLE notification_deliveries');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE user_suspensions');
    }
}
