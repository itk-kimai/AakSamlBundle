<?php

declare(strict_types=1);

namespace KimaiPlugin\AakSamlBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240701112200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_964F38AFFA0C224 ON kimai2_aak_saml_team_meta');
        $this->addSql('ALTER TABLE
          kimai2_aak_saml_team_meta
        ADD
          org_unit_id INT NOT NULL AFTER team_id,
        ADD
          dept_ids JSON NOT NULL COMMENT \'(DC2Type:json)\',
        CHANGE
          company_id company_id INT DEFAULT NULL,
        CHANGE
          company_name company_name VARCHAR(255) DEFAULT NULL,
        CHANGE
          division_id division_id INT DEFAULT NULL,
        CHANGE
          division_name division_name VARCHAR(255) DEFAULT NULL,
        CHANGE
          dept_id dept_id INT DEFAULT NULL,
        CHANGE
          dept_name dept_name VARCHAR(255) DEFAULT NULL,
        CHANGE
          sub_dept_id sub_dept_id INT DEFAULT NULL,
        CHANGE
          sub_dept_name sub_dept_name VARCHAR(255) DEFAULT NULL,
        CHANGE
          office_id office_id INT DEFAULT NULL,
        CHANGE
          office_name office_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE kimai2_aak_saml_team_meta meta set meta.org_unit_id = meta.office_id, meta.dept_ids = "[]"');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_964F38A8BC224C3 ON kimai2_aak_saml_team_meta (org_unit_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_964F38A8BC224C3 ON kimai2_aak_saml_team_meta');
        $this->addSql('ALTER TABLE
          kimai2_aak_saml_team_meta
        DROP
          org_unit_id,
        DROP
          dept_ids,
        CHANGE
          company_id company_id INT NOT NULL,
        CHANGE
          company_name company_name VARCHAR(255) NOT NULL,
        CHANGE
          division_id division_id INT NOT NULL,
        CHANGE
          division_name division_name VARCHAR(255) NOT NULL,
        CHANGE
          dept_id dept_id INT NOT NULL,
        CHANGE
          dept_name dept_name VARCHAR(255) NOT NULL,
        CHANGE
          sub_dept_id sub_dept_id INT NOT NULL,
        CHANGE
          sub_dept_name sub_dept_name VARCHAR(255) NOT NULL,
        CHANGE
          office_id office_id INT NOT NULL,
        CHANGE
          office_name office_name VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_964F38AFFA0C224 ON kimai2_aak_saml_team_meta (office_id)');
    }
}
