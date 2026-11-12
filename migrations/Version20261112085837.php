<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112085837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: paths, quests, tags, daily challenges, sign roles, users, settings';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE daily_challenges (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, is_manual TINYINT NOT NULL, path_id INT NOT NULL, quest_id INT NOT NULL, UNIQUE INDEX uniq_daily_date_path (date, path_id), INDEX IDX_FA565A83D96C566B (path_id), INDEX IDX_FA565A83209E9EF4 (quest_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE paths (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, description LONGTEXT NOT NULL, symbol VARCHAR(32) NOT NULL, color VARCHAR(7) NOT NULL, sort_order INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8BBA8611989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE quests (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, slug VARCHAR(180) NOT NULL, summary VARCHAR(400) NOT NULL, body LONGTEXT NOT NULL, difficulty SMALLINT NOT NULL, duration VARCHAR(100) NOT NULL, materials LONGTEXT DEFAULT NULL, pdf_path VARCHAR(255) DEFAULT NULL, cover_image_path VARCHAR(255) DEFAULT NULL, is_published TINYINT NOT NULL, is_daily_eligible TINYINT NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, path_id INT NOT NULL, UNIQUE INDEX UNIQ_989E5D34989D9B62 (slug), INDEX idx_quests_published (is_published, published_at), INDEX IDX_989E5D34D96C566B (path_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE quest_path (quest_id INT NOT NULL, path_id INT NOT NULL, INDEX IDX_55E27FD209E9EF4 (quest_id), INDEX IDX_55E27FDD96C566B (path_id), PRIMARY KEY (quest_id, path_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE quest_tag (quest_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_D2AD778B209E9EF4 (quest_id), INDEX IDX_D2AD778BBAD26311 (tag_id), PRIMARY KEY (quest_id, tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE settings (setting_key VARCHAR(100) NOT NULL, value LONGTEXT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (setting_key)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE sign_roles (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(60) NOT NULL, slug VARCHAR(80) NOT NULL, sort_order INT NOT NULL, UNIQUE INDEX UNIQ_82579CF0989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE tags (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(60) NOT NULL, slug VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_6FBC9426989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, display_name VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE daily_challenges ADD CONSTRAINT FK_FA565A83D96C566B FOREIGN KEY (path_id) REFERENCES paths (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE daily_challenges ADD CONSTRAINT FK_FA565A83209E9EF4 FOREIGN KEY (quest_id) REFERENCES quests (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quests ADD CONSTRAINT FK_989E5D34D96C566B FOREIGN KEY (path_id) REFERENCES paths (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE quest_path ADD CONSTRAINT FK_55E27FD209E9EF4 FOREIGN KEY (quest_id) REFERENCES quests (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quest_path ADD CONSTRAINT FK_55E27FDD96C566B FOREIGN KEY (path_id) REFERENCES paths (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quest_tag ADD CONSTRAINT FK_D2AD778B209E9EF4 FOREIGN KEY (quest_id) REFERENCES quests (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quest_tag ADD CONSTRAINT FK_D2AD778BBAD26311 FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE daily_challenges DROP FOREIGN KEY FK_FA565A83D96C566B');
        $this->addSql('ALTER TABLE daily_challenges DROP FOREIGN KEY FK_FA565A83209E9EF4');
        $this->addSql('ALTER TABLE quests DROP FOREIGN KEY FK_989E5D34D96C566B');
        $this->addSql('ALTER TABLE quest_path DROP FOREIGN KEY FK_55E27FD209E9EF4');
        $this->addSql('ALTER TABLE quest_path DROP FOREIGN KEY FK_55E27FDD96C566B');
        $this->addSql('ALTER TABLE quest_tag DROP FOREIGN KEY FK_D2AD778B209E9EF4');
        $this->addSql('ALTER TABLE quest_tag DROP FOREIGN KEY FK_D2AD778BBAD26311');
        $this->addSql('DROP TABLE daily_challenges');
        $this->addSql('DROP TABLE paths');
        $this->addSql('DROP TABLE quests');
        $this->addSql('DROP TABLE quest_path');
        $this->addSql('DROP TABLE quest_tag');
        $this->addSql('DROP TABLE settings');
        $this->addSql('DROP TABLE sign_roles');
        $this->addSql('DROP TABLE tags');
        $this->addSql('DROP TABLE users');
    }
}
