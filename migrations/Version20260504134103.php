<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260504134103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE traitement (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, posologie VARCHAR(255) DEFAULT NULL, date_debut DATE NOT NULL, date_fin DATE DEFAULT NULL, created_at DATETIME NOT NULL, animal_id INT NOT NULL, veterinaire_id INT NOT NULL, INDEX IDX_2A356D278E962C16 (animal_id), INDEX IDX_2A356D275C80924 (veterinaire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE traitement ADD CONSTRAINT FK_2A356D278E962C16 FOREIGN KEY (animal_id) REFERENCES animal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE traitement ADD CONSTRAINT FK_2A356D275C80924 FOREIGN KEY (veterinaire_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE traitement DROP FOREIGN KEY FK_2A356D278E962C16');
        $this->addSql('ALTER TABLE traitement DROP FOREIGN KEY FK_2A356D275C80924');
        $this->addSql('DROP TABLE traitement');
    }
}
