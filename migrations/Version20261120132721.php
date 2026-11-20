<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120132721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Jokes, anecdotes and memes of the day';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE jokes (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, text LONGTEXT DEFAULT NULL, image_path VARCHAR(255) DEFAULT NULL, image_alt VARCHAR(255) DEFAULT NULL, is_active TINYINT NOT NULL, last_shown_on DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_jokes_shown (last_shown_on), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE jokes');
    }
}
