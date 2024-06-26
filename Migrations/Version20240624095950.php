<?php

declare(strict_types=1);

namespace KimaiPlugin\AakSamlBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240624095950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE kimai2_aak_saml_team_meta (
          id INT AUTO_INCREMENT NOT NULL,
          team_id INT NOT NULL,
          manager_email VARCHAR(255) NOT NULL,
          manager_name VARCHAR(255) NOT NULL,
          company_id INT NOT NULL,
          company_name VARCHAR(255) NOT NULL,
          division_id INT NOT NULL,
          division_name VARCHAR(255) NOT NULL,
          dept_id INT NOT NULL,
          dept_name VARCHAR(255) NOT NULL,
          sub_dept_id INT NOT NULL,
          sub_dept_name VARCHAR(255) NOT NULL,
          office_id INT NOT NULL,
          office_name VARCHAR(255) NOT NULL,
          UNIQUE INDEX UNIQ_964F38ACE4DBC4E (manager_email),
          UNIQUE INDEX UNIQ_964F38A296CD8AE (team_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE
          kimai2_aak_saml_team_meta
        ADD
          CONSTRAINT FK_964F38A296CD8AE FOREIGN KEY (team_id) REFERENCES kimai2_teams (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE kimai2_aak_saml_team_meta DROP FOREIGN KEY FK_964F38A296CD8AE');

        $this->addSql('DROP TABLE kimai2_aak_saml_team_meta');
    }
}
