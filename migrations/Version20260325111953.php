<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260325111953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE animal DROP FOREIGN KEY `FK_6AAB231F7E3C61F9`');
        $this->addSql('DROP INDEX IDX_6AAB231F7E3C61F9 ON animal');
        $this->addSql('ALTER TABLE animal ADD remarque LONGTEXT DEFAULT NULL, CHANGE espece espece VARCHAR(255) DEFAULT NULL, CHANGE vaccin_ajour vaccin_ajour TINYINT DEFAULT NULL, CHANGE poids poids VARCHAR(50) DEFAULT NULL, CHANGE owner_id proprietaire_id INT NOT NULL');
        $this->addSql('ALTER TABLE animal ADD CONSTRAINT FK_6AAB231F76C50E4A FOREIGN KEY (proprietaire_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_6AAB231F76C50E4A ON animal (proprietaire_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE animal DROP FOREIGN KEY FK_6AAB231F76C50E4A');
        $this->addSql('DROP INDEX IDX_6AAB231F76C50E4A ON animal');
        $this->addSql('ALTER TABLE animal DROP remarque, CHANGE espece espece VARCHAR(255) NOT NULL, CHANGE vaccin_ajour vaccin_ajour TINYINT NOT NULL, CHANGE poids poids VARCHAR(100) DEFAULT NULL, CHANGE proprietaire_id owner_id INT NOT NULL');
        $this->addSql('ALTER TABLE animal ADD CONSTRAINT `FK_6AAB231F7E3C61F9` FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_6AAB231F7E3C61F9 ON animal (owner_id)');
    }
}
