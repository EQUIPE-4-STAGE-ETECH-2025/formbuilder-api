<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250930085009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration initiale - Création du schéma complet de la base de données FormBuilder';
    }

    public function up(Schema $schema): void
    {
        // Création des tables principales
        $this->addSql('CREATE TABLE audit_log (id UUID NOT NULL, admin_id UUID NOT NULL, target_user_id UUID NOT NULL, action VARCHAR(255) NOT NULL, reason TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F6E1C0F5642B8210 ON audit_log (admin_id)');
        $this->addSql('CREATE INDEX IDX_F6E1C0F56C066AFE ON audit_log (target_user_id)');
        $this->addSql('COMMENT ON COLUMN audit_log.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN audit_log.admin_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN audit_log.target_user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN audit_log.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE black_listed_token (id UUID NOT NULL, token TEXT NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7F4339965F37A13B ON black_listed_token (token)');
        $this->addSql('COMMENT ON COLUMN black_listed_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN black_listed_token.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE feature (id UUID NOT NULL, code VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN feature.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE form (id UUID NOT NULL, user_id UUID NOT NULL, title VARCHAR(255) NOT NULL, description TEXT NOT NULL, status VARCHAR(20) NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5288FD4FA76ED395 ON form (user_id)');
        $this->addSql('COMMENT ON COLUMN form.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN form.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN form.published_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN form.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN form.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE form_field (id UUID NOT NULL, form_version_id UUID NOT NULL, label VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, is_required BOOLEAN NOT NULL, options JSON NOT NULL, position INT NOT NULL, validation_rules JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D8B2E19B427B6B91 ON form_field (form_version_id)');
        $this->addSql('COMMENT ON COLUMN form_field.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN form_field.form_version_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE form_version (id UUID NOT NULL, form_id UUID NOT NULL, version_number INT NOT NULL, schema JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8E70B3F85FF69B7D ON form_version (form_id)');
        $this->addSql('COMMENT ON COLUMN form_version.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN form_version.form_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN form_version.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE plan (id UUID NOT NULL, name VARCHAR(255) NOT NULL, price_cents INT NOT NULL, stripe_product_id VARCHAR(255) DEFAULT NULL, stripe_price_id VARCHAR(255) DEFAULT NULL, max_forms INT NOT NULL, max_submissions_per_month INT NOT NULL, max_storage_mb INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN plan.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE plan_feature (id UUID NOT NULL, plan_id UUID NOT NULL, feature_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A1683D6EE899029B ON plan_feature (plan_id)');
        $this->addSql('CREATE INDEX IDX_A1683D6E60E4B879 ON plan_feature (feature_id)');
        $this->addSql('COMMENT ON COLUMN plan_feature.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN plan_feature.plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN plan_feature.feature_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE quota_status (id UUID NOT NULL, user_id UUID NOT NULL, month DATE NOT NULL, form_count INT NOT NULL, submission_count INT NOT NULL, storage_used_mb INT NOT NULL, notified80 BOOLEAN NOT NULL, notified100 BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C5F2C5A5A76ED395 ON quota_status (user_id)');
        $this->addSql('COMMENT ON COLUMN quota_status.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN quota_status.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE submission (id UUID NOT NULL, form_id UUID NOT NULL, data JSON NOT NULL, submitted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ip_address VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DB055AF35FF69B7D ON submission (form_id)');
        $this->addSql('COMMENT ON COLUMN submission.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN submission.form_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN submission.submitted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE subscription (id UUID NOT NULL, user_id UUID NOT NULL, plan_id UUID NOT NULL, stripe_subscription_id VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A3C664D3A76ED395 ON subscription (user_id)');
        $this->addSql('CREATE INDEX IDX_A3C664D3E899029B ON subscription (plan_id)');
        $this->addSql('COMMENT ON COLUMN subscription.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription.plan_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscription.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscription.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, is_email_verified BOOLEAN NOT NULL, role VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('COMMENT ON COLUMN users.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.updated_at IS \'(DC2Type:datetime_immutable)\'');
        
        // Création des contraintes de clés étrangères
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F5642B8210 FOREIGN KEY (admin_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F56C066AFE FOREIGN KEY (target_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE form ADD CONSTRAINT FK_5288FD4FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE form_field ADD CONSTRAINT FK_D8B2E19B427B6B91 FOREIGN KEY (form_version_id) REFERENCES form_version (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE form_version ADD CONSTRAINT FK_8E70B3F85FF69B7D FOREIGN KEY (form_id) REFERENCES form (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE plan_feature ADD CONSTRAINT FK_A1683D6EE899029B FOREIGN KEY (plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE plan_feature ADD CONSTRAINT FK_A1683D6E60E4B879 FOREIGN KEY (feature_id) REFERENCES feature (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quota_status ADD CONSTRAINT FK_C5F2C5A5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE submission ADD CONSTRAINT FK_DB055AF35FF69B7D FOREIGN KEY (form_id) REFERENCES form (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3E899029B FOREIGN KEY (plan_id) REFERENCES plan (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // Suppression des contraintes de clés étrangères
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_F6E1C0F5642B8210');
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_F6E1C0F56C066AFE');
        $this->addSql('ALTER TABLE form DROP CONSTRAINT FK_5288FD4FA76ED395');
        $this->addSql('ALTER TABLE form_field DROP CONSTRAINT FK_D8B2E19B427B6B91');
        $this->addSql('ALTER TABLE form_version DROP CONSTRAINT FK_8E70B3F85FF69B7D');
        $this->addSql('ALTER TABLE plan_feature DROP CONSTRAINT FK_A1683D6EE899029B');
        $this->addSql('ALTER TABLE plan_feature DROP CONSTRAINT FK_A1683D6E60E4B879');
        $this->addSql('ALTER TABLE quota_status DROP CONSTRAINT FK_C5F2C5A5A76ED395');
        $this->addSql('ALTER TABLE submission DROP CONSTRAINT FK_DB055AF35FF69B7D');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D3A76ED395');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D3E899029B');
        
        // Suppression des tables
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE black_listed_token');
        $this->addSql('DROP TABLE feature');
        $this->addSql('DROP TABLE form');
        $this->addSql('DROP TABLE form_field');
        $this->addSql('DROP TABLE form_version');
        $this->addSql('DROP TABLE plan');
        $this->addSql('DROP TABLE plan_feature');
        $this->addSql('DROP TABLE quota_status');
        $this->addSql('DROP TABLE submission');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE users');
    }
}
