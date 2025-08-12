<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812110323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // 1) Ajouter status nullable
        $this->addSql('ALTER TABLE subscription ADD status VARCHAR(20) DEFAULT NULL');

        // 2) Remplir status en fonction de is_active
        $this->addSql("UPDATE subscription SET status = CASE WHEN is_active THEN 'ACTIVE' ELSE 'CANCELLED' END");

        // 3) Passer status en NOT NULL et mettre valeur par défaut
        $this->addSql("ALTER TABLE subscription ALTER COLUMN status SET NOT NULL");
        $this->addSql("ALTER TABLE subscription ALTER COLUMN status SET DEFAULT 'ACTIVE'");

        // 4) Supprimer is_active
        $this->addSql('ALTER TABLE subscription DROP COLUMN is_active');
    }


    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE subscription ADD is_active BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE subscription DROP status');
    }
}
