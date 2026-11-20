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
final class Version20261120145307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Visitor accounts: journal entries, password reset token, privacy page texts';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE journal_entries (id INT AUTO_INCREMENT NOT NULL, quest_title VARCHAR(160) NOT NULL, completed_on DATE NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, quest_id INT DEFAULT NULL, INDEX idx_journal_user_date (user_id, completed_on), INDEX IDX_FD43FD20A76ED395 (user_id), INDEX IDX_FD43FD20209E9EF4 (quest_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE journal_entries ADD CONSTRAINT FK_FD43FD20A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE journal_entries ADD CONSTRAINT FK_FD43FD20209E9EF4 FOREIGN KEY (quest_id) REFERENCES quests (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE users ADD reset_token_hash VARCHAR(64) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9DC2A7551 ON users (reset_token_hash)');

        // first version of the privacy page; edited in the admin afterwards (never overwritten)
        foreach ([Setting::PRIVACY_TITLE => ExampleContent::PRIVACY_TITLE, Setting::PRIVACY_BODY => trim(ExampleContent::PRIVACY_BODY)] as $key => $value) {
            $this->addSql('INSERT IGNORE INTO settings (setting_key, value, updated_at) VALUES (?, ?, NOW())', [$key, $value]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE journal_entries DROP FOREIGN KEY FK_FD43FD20A76ED395');
        $this->addSql('ALTER TABLE journal_entries DROP FOREIGN KEY FK_FD43FD20209E9EF4');
        $this->addSql('DROP TABLE journal_entries');
        $this->addSql('DROP INDEX UNIQ_1483A5E9DC2A7551 ON users');
        $this->addSql('ALTER TABLE users DROP reset_token_hash, DROP reset_token_expires_at');
    }
}
