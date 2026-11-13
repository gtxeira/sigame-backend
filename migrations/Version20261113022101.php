<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cria as conquistas do usuário e registra a LENDA para quem já está no nível 10.
 */
final class Version20261113022101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria user_achievements e faz backfill da conquista LENDA para o nível 10.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_achievements (user_id UUID NOT NULL, code VARCHAR(40) NOT NULL, achieved_at TIMESTAMP(6) WITH TIME ZONE NOT NULL, celebrated_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (user_id, code))');
        $this->addSql("INSERT INTO user_achievements (user_id, code, achieved_at) SELECT user_id, 'LENDA', updated_at FROM user_progress WHERE level >= 10 ON CONFLICT (user_id, code) DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_achievements');
    }
}
