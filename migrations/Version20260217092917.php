<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217092917 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE day_of_work CHANGE day jour_id INT NOT NULL');
        $this->addSql('ALTER TABLE day_of_work ADD CONSTRAINT FK_EB39031220C6AD0 FOREIGN KEY (jour_id) REFERENCES jour (id)');
        $this->addSql('CREATE INDEX IDX_EB39031220C6AD0 ON day_of_work (jour_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE day_of_work DROP FOREIGN KEY FK_EB39031220C6AD0');
        $this->addSql('DROP INDEX IDX_EB39031220C6AD0 ON day_of_work');
        $this->addSql('ALTER TABLE day_of_work CHANGE jour_id day INT NOT NULL');
    }
}
