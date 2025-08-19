<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250819082603 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE form_token ADD type VARCHAR(50) NOT NULL DEFAULT \'embed\'');
        $this->addSql('ALTER TABLE form_token ADD is_active BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE form_token RENAME COLUMN jwt TO token');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE form_token DROP type');
        $this->addSql('ALTER TABLE form_token DROP is_active');
        $this->addSql('ALTER TABLE form_token RENAME COLUMN token TO jwt');
    }
}
