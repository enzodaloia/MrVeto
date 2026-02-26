<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217142013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE horaire ADD morning_start TIME DEFAULT NULL, ADD morning_end TIME DEFAULT NULL, ADD afternoon_start TIME DEFAULT NULL, ADD afternoon_end TIME DEFAULT NULL, DROP start_time, DROP end_time');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE horaire ADD start_time TIME DEFAULT NULL, ADD end_time TIME DEFAULT NULL, DROP morning_start, DROP morning_end, DROP afternoon_start, DROP afternoon_end');
    }
}
