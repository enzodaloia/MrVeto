<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217084457 extends AbstractMigration
{
    public function getDescription(): string
    {
    return 'Insert default days of week into jour table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("
        INSERT IGNORE INTO jour (id, libelle, ordre) VALUES
        (1, 'Lundi', 1),
        (2, 'Mardi', 2),
        (3, 'Mercredi', 3),
        (4, 'Jeudi', 4),
        (5, 'Vendredi', 5),
        (6, 'Samedi', 6),
        (7, 'Dimanche', 7)
    ");

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
            $this->addSql("DELETE FROM jour WHERE id IN (1,2,3,4,5,6,7)");


    }
}
