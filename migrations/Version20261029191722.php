<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Substitui o perfil JSON por colunas escalares e cria a tabela de modalidades do usuário.
 */
final class Version20261029191722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Substitui users.profile JSON por height_cm, weight_kg e photo_url, e cria user_modalities.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_modalities (modality VARCHAR(20) NOT NULL, is_primary BOOLEAN DEFAULT FALSE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (user_id, modality))');
        $this->addSql('CREATE INDEX idx_user_modalities_modality ON user_modalities (modality)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_modalities_primary ON user_modalities (user_id) WHERE is_primary');
        $this->addSql('CREATE INDEX IDX_3872422A76ED395 ON user_modalities (user_id)');
        $this->addSql('ALTER TABLE user_modalities ADD CONSTRAINT FK_3872422A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE users ADD height_cm INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD weight_kg DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD photo_url VARCHAR(2048) DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE users
            SET height_cm = ROUND((profile->>'height')::numeric * 100)::integer,
                weight_kg = (profile->>'weight')::double precision,
                photo_url = profile->>'photoUrl'
            WHERE profile IS NOT NULL
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO user_modalities (user_id, modality, is_primary)
            SELECT u.id, m.value, COALESCE((u.profile->>'primaryModality') = m.value, FALSE)
            FROM users u
            CROSS JOIN LATERAL json_array_elements_text(u.profile -> 'modalities') AS m(value)
            WHERE u.profile IS NOT NULL
            ON CONFLICT (user_id, modality) DO NOTHING
            SQL);

        $this->addSql('ALTER TABLE users DROP profile');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD profile JSON DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE users u
            SET profile = json_build_object(
                'weight', u.weight_kg,
                'height', CASE
                    WHEN u.height_cm IS NULL THEN NULL
                    ELSE (u.height_cm / 100.0)::double precision
                END,
                'photoUrl', u.photo_url,
                'modalities', COALESCE((
                    SELECT json_agg(um.modality ORDER BY um.modality)
                    FROM user_modalities um
                    WHERE um.user_id = u.id
                ), '[]'::json),
                'primaryModality', (
                    SELECT um.modality
                    FROM user_modalities um
                    WHERE um.user_id = u.id AND um.is_primary
                    LIMIT 1
                )
            )
            WHERE EXISTS (SELECT 1 FROM user_modalities um WHERE um.user_id = u.id)
               OR u.height_cm IS NOT NULL
               OR u.weight_kg IS NOT NULL
               OR u.photo_url IS NOT NULL
            SQL);

        $this->addSql('ALTER TABLE user_modalities DROP CONSTRAINT FK_3872422A76ED395');
        $this->addSql('DROP TABLE user_modalities');
        $this->addSql('ALTER TABLE users DROP height_cm');
        $this->addSql('ALTER TABLE users DROP weight_kg');
        $this->addSql('ALTER TABLE users DROP photo_url');
    }
}
