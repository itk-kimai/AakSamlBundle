<?php

declare(strict_types=1);

namespace KimaiPlugin\AakSamlBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240709120116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_964F38A8BC224C3 ON kimai2_aak_saml_team_meta');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_964F38A8BC224C3CE4DBC4E ON kimai2_aak_saml_team_meta (org_unit_id, manager_email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_964F38A8BC224C3CE4DBC4E ON kimai2_aak_saml_team_meta');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_964F38A8BC224C3 ON kimai2_aak_saml_team_meta (org_unit_id)');
    }
}
