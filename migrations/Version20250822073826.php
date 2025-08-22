<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250822073826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE form_token ALTER type DROP DEFAULT');
        $this->addSql('ALTER TABLE form_token ALTER is_active DROP DEFAULT');
        $this->addSql('ALTER TABLE subscription ADD status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE subscription DROP is_active');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE subscription ADD is_active BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE subscription DROP status');
        $this->addSql('ALTER TABLE form_token ALTER type SET DEFAULT \'embed\'');
        $this->addSql('ALTER TABLE form_token ALTER is_active SET DEFAULT true');
    }
}
