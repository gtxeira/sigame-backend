<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261109224939 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria activities, activity_attendances e activity_reschedules.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activities (id UUID NOT NULL, group_id UUID NOT NULL, created_by UUID NOT NULL, title VARCHAR(100) NOT NULL, description VARCHAR(500) DEFAULT NULL, starts_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, ends_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, estimated_duration_minutes INT DEFAULT NULL, meeting_point VARCHAR(255) NOT NULL, route VARCHAR(500) NOT NULL, estimated_distance_meters INT NOT NULL, status VARCHAR(20) NOT NULL, started_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, started_by UUID DEFAULT NULL, auto_close_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, finished_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, finished_by UUID DEFAULT NULL, finished_automatically BOOLEAN DEFAULT false NOT NULL, cancelled_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, cancelled_by UUID DEFAULT NULL, cancelled_automatically BOOLEAN DEFAULT false NOT NULL, cancellation_reason VARCHAR(500) DEFAULT NULL, invalidated_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, invalidated_by UUID DEFAULT NULL, invalidation_reason VARCHAR(500) DEFAULT NULL, created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_activities_group_starts_at ON activities (group_id, starts_at)');
        $this->addSql('CREATE INDEX idx_activities_status_auto_close_at ON activities (status, auto_close_at)');
        $this->addSql('CREATE INDEX idx_activities_created_by ON activities (created_by)');
        $this->addSql('CREATE TABLE activity_attendances (id UUID NOT NULL, user_id UUID NOT NULL, status VARCHAR(20) NOT NULL, enrolled_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, checked_in_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, validated_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, validated_by UUID DEFAULT NULL, cancelled_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, cancelled_by UUID DEFAULT NULL, created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, activity_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_activity_attendances_user_status ON activity_attendances (user_id, status)');
        $this->addSql('CREATE INDEX idx_activity_attendances_activity_status ON activity_attendances (activity_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_activity_attendances_active ON activity_attendances (activity_id, user_id) WHERE (status IN (\'enrolled\', \'checked_in\', \'validated\'))');
        $this->addSql('CREATE INDEX IDX_92E5E5D881C06096 ON activity_attendances (activity_id)');
        $this->addSql('CREATE TABLE activity_reschedules (id UUID NOT NULL, changed_by UUID NOT NULL, previous_starts_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, previous_ends_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, new_starts_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, new_ends_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, reason VARCHAR(500) DEFAULT NULL, changed_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, activity_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_activity_reschedules_activity ON activity_reschedules (activity_id)');
        $this->addSql('ALTER TABLE activity_attendances ADD CONSTRAINT FK_92E5E5D881C06096 FOREIGN KEY (activity_id) REFERENCES activities (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE activity_reschedules ADD CONSTRAINT FK_23BB37C681C06096 FOREIGN KEY (activity_id) REFERENCES activities (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_attendances DROP CONSTRAINT FK_92E5E5D881C06096');
        $this->addSql('ALTER TABLE activity_reschedules DROP CONSTRAINT FK_23BB37C681C06096');
        $this->addSql('DROP TABLE activities');
        $this->addSql('DROP TABLE activity_attendances');
        $this->addSql('DROP TABLE activity_reschedules');
    }
}
