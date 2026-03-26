<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cabinet and cabinet_user tables for veterinary cabinet memberships.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cabinet (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, adresse VARCHAR(255) DEFAULT NULL, ville VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(20) DEFAULT NULL, telephone VARCHAR(30) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cabinet_user (id INT AUTO_INCREMENT NOT NULL, cabinet_id INT NOT NULL, user_id INT NOT NULL, role_in_cabinet VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_85E52884B8B5D607 (cabinet_id), INDEX IDX_85E52884A76ED395 (user_id), UNIQUE INDEX uniq_cabinet_user_pair (cabinet_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE cabinet_user ADD CONSTRAINT FK_85E52884B8B5D607 FOREIGN KEY (cabinet_id) REFERENCES cabinet (id)');
        $this->addSql('ALTER TABLE cabinet_user ADD CONSTRAINT FK_85E52884A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cabinet_user DROP FOREIGN KEY FK_85E52884B8B5D607');
        $this->addSql('ALTER TABLE cabinet_user DROP FOREIGN KEY FK_85E52884A76ED395');
        $this->addSql('DROP TABLE cabinet_user');
        $this->addSql('DROP TABLE cabinet');
    }
}
