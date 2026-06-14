<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout colonne is_urgentiste sur vet_profile';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vet_profile ADD is_urgentiste TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vet_profile DROP is_urgentiste');
    }
}
