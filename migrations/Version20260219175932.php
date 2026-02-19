<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219175932 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE day_of_work (id INT AUTO_INCREMENT NOT NULL, is_working TINYINT DEFAULT 1 NOT NULL, user_id INT NOT NULL, jour_id INT NOT NULL, INDEX IDX_EB39031A76ED395 (user_id), INDEX IDX_EB39031220C6AD0 (jour_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE horaire (id INT AUTO_INCREMENT NOT NULL, morning_start TIME DEFAULT NULL, morning_end TIME DEFAULT NULL, afternoon_start TIME DEFAULT NULL, afternoon_end TIME DEFAULT NULL, day_of_work_id INT NOT NULL, INDEX IDX_BBC83DB660C122B5 (day_of_work_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE jour (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(50) NOT NULL, ordre INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE day_of_work ADD CONSTRAINT FK_EB39031A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE day_of_work ADD CONSTRAINT FK_EB39031220C6AD0 FOREIGN KEY (jour_id) REFERENCES jour (id)');
        $this->addSql('ALTER TABLE horaire ADD CONSTRAINT FK_BBC83DB660C122B5 FOREIGN KEY (day_of_work_id) REFERENCES day_of_work (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE day_of_work DROP FOREIGN KEY FK_EB39031A76ED395');
        $this->addSql('ALTER TABLE day_of_work DROP FOREIGN KEY FK_EB39031220C6AD0');
        $this->addSql('ALTER TABLE horaire DROP FOREIGN KEY FK_BBC83DB660C122B5');
        $this->addSql('DROP TABLE day_of_work');
        $this->addSql('DROP TABLE horaire');
        $this->addSql('DROP TABLE jour');
    }
}
