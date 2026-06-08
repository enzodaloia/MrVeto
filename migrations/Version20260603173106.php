<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260603173106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rendez_vous ADD compte_rendu LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE specialite RENAME INDEX uniq_specialite_slug TO UNIQ_E7D6FCC1989D9B62');
        $this->addSql('ALTER TABLE vet_profile DROP FOREIGN KEY `FK_VET_PROFILE_USER`');
        $this->addSql('ALTER TABLE vet_profile ADD CONSTRAINT FK_77ACE0AAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE vet_profile RENAME INDEX uniq_vet_profile_user TO UNIQ_77ACE0AAA76ED395');
        $this->addSql('ALTER TABLE vet_profile_specialite RENAME INDEX idx_vps_vp TO IDX_4A80A7533B001DE');
        $this->addSql('ALTER TABLE vet_profile_specialite RENAME INDEX idx_vps_sp TO IDX_4A80A752195E0F0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rendez_vous DROP compte_rendu');
        $this->addSql('ALTER TABLE specialite RENAME INDEX uniq_e7d6fcc1989d9b62 TO UNIQ_specialite_slug');
        $this->addSql('ALTER TABLE vet_profile DROP FOREIGN KEY FK_77ACE0AAA76ED395');
        $this->addSql('ALTER TABLE vet_profile ADD CONSTRAINT `FK_VET_PROFILE_USER` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vet_profile RENAME INDEX uniq_77ace0aaa76ed395 TO UNIQ_VET_PROFILE_USER');
        $this->addSql('ALTER TABLE vet_profile_specialite RENAME INDEX idx_4a80a7533b001de TO IDX_vps_vp');
        $this->addSql('ALTER TABLE vet_profile_specialite RENAME INDEX idx_4a80a752195e0f0 TO IDX_vps_sp');
    }
}
