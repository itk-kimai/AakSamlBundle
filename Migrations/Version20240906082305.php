<?php

declare(strict_types=1);

namespace KimaiPlugin\AakSamlBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240906082305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE
          kimai2_aak_saml_claims_log
        ADD
          last_saml_login_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'
        AFTER
          saml_user_email');
        $this->addSql('CREATE VIEW 
          kimai2_aak_saml_claims_users_view 
        AS SELECT 
          u.username, u.alias, u.last_login, log.last_saml_login_at, log.logged_at, log.claims, log.exceptionMessage,
          sub.username AS supervisor_email, sub.alias AS supervisor_name 
        FROM 
          kimai2_users u 
        LEFT JOIN 
          kimai2_aak_saml_claims_log log on u.username = log.saml_user_email
        INNER JOIN 
          kimai2_users sub ON u.supervisor_id = sub.id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai2_aak_saml_claims_log DROP last_saml_login_at');
    }
}
