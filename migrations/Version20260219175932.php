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
        return 'No-op: duplicate schema snapshot replaced to keep migration chain consistent.';
    }

    public function up(Schema $schema): void
    {
        // Intentionally left blank.
        // This migration duplicated tables already created by previous migrations.
    }

    public function down(Schema $schema): void
    {
        // Intentionally left blank.
    }
}
