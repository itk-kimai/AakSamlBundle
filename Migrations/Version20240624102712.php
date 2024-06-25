<?php

declare(strict_types=1);

namespace KimaiPlugin2\AakSamlBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240624102712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_964F38ACE4DBC4E ON kimai2_aak_saml_team_meta');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_964F38AFFA0C224 ON kimai2_aak_saml_team_meta (office_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_964F38AFFA0C224 ON kimai2_aak_saml_team_meta');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_964F38ACE4DBC4E ON kimai2_aak_saml_team_meta (manager_email)');
    }
}
