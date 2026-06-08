<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260603182510 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE animal CHANGE slug slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE cabinet CHANGE slug slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE cabinet_user CHANGE slug slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE day_of_work CHANGE slug slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE horaire CHANGE slug slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE jour CHANGE slug slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD compte_rendu_pdf_data LONGBLOB DEFAULT NULL, CHANGE slug slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reset_password_request CHANGE slug slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE traitement CHANGE slug slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE slug slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE vet_profile DROP is_urgentiste');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE animal CHANGE slug slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE cabinet CHANGE slug slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE cabinet_user CHANGE slug slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE day_of_work CHANGE slug slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE horaire CHANGE slug slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE jour CHANGE slug slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE rendez_vous DROP compte_rendu_pdf_data, CHANGE slug slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE reset_password_request CHANGE slug slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE traitement CHANGE slug slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE slug slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE vet_profile ADD is_urgentiste TINYINT DEFAULT 0 NOT NULL');
    }
}
