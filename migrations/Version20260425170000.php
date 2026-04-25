<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add appointment action metadata for navbar notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous ADD previous_date_heure DATETIME DEFAULT NULL, ADD last_action_type VARCHAR(20) DEFAULT NULL, ADD last_action_by_role VARCHAR(20) DEFAULT NULL, ADD last_action_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous DROP previous_date_heure, DROP last_action_type, DROP last_action_by_role, DROP last_action_at');
    }
}
