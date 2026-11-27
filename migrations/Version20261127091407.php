<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Setting;
use App\Seed\ExampleContent;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261127091407 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Wall of public comments ("Събираме идеи"): walls, wall_comments, the first two comments, a paragraph in the privacy text';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE wall_comments (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(40) NOT NULL, message LONGTEXT NOT NULL, os VARCHAR(60) NOT NULL, via VARCHAR(10) NOT NULL, ip_hash CHAR(64) NOT NULL, message_hash CHAR(64) NOT NULL, user_agent VARCHAR(255) DEFAULT NULL, is_hidden TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, wall_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX idx_wall_comments_wall (wall_id, is_hidden, created_at), INDEX idx_wall_comments_sender (ip_hash, created_at), INDEX IDX_7D463BDCC33923F1 (wall_id), INDEX IDX_7D463BDC727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE walls (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(100) NOT NULL, title VARCHAR(160) NOT NULL, published_at DATETIME NOT NULL, is_open TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_4FC36DCD989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE wall_comments ADD CONSTRAINT FK_7D463BDCC33923F1 FOREIGN KEY (wall_id) REFERENCES walls (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wall_comments ADD CONSTRAINT FK_7D463BDC727ACA70 FOREIGN KEY (parent_id) REFERENCES wall_comments (id) ON DELETE CASCADE');

        // the first wall and its first two comments (they have no sender, so the hash is of a fixed word)
        $this->addSql('INSERT INTO walls (slug, title, published_at, is_open) VALUES (?, ?, ?, 1)', [ExampleContent::WALL_SLUG, ExampleContent::WALL_TITLE, ExampleContent::WALL_PUBLISHED_AT]);
        foreach (ExampleContent::WALL_COMMENTS as $comment) {
            $this->addSql(
                'INSERT INTO wall_comments (wall_id, parent_id, name, message, os, via, ip_hash, message_hash, user_agent, is_hidden, created_at) SELECT id, NULL, ?, ?, ?, ?, ?, ?, NULL, 0, ? FROM walls WHERE slug = ?',
                [ExampleContent::WALL_COMMENT_NAME, $comment['message'], ExampleContent::WALL_COMMENT_OS, 'form', hash('sha256', 'seed'), hash('sha256', $comment['message']), $comment['created_at'], ExampleContent::WALL_SLUG],
            );
        }

        // the privacy text is edited in the admin, so the new paragraph is only added at its end, once
        $this->addSql(
            'UPDATE settings SET value = CONCAT(value, ?), updated_at = NOW() WHERE setting_key = ? AND value NOT LIKE ?',
            [ExampleContent::PRIVACY_COMMENTS, Setting::PRIVACY_BODY, '%Коментарите в блога са публични%'],
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE wall_comments DROP FOREIGN KEY FK_7D463BDCC33923F1');
        $this->addSql('ALTER TABLE wall_comments DROP FOREIGN KEY FK_7D463BDC727ACA70');
        $this->addSql('DROP TABLE wall_comments');
        $this->addSql('DROP TABLE walls');
    }
}
