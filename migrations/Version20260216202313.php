<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216202313 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op: duplicate baseline migration kept for history compatibility.';
    }

    public function up(Schema $schema): void
    {
        // Intentionally left blank.
        // This migration duplicated schema creation already performed by earlier migrations.
        // Keeping it as a no-op preserves migration numbering across environments.
    }

    public function down(Schema $schema): void
    {
        // Intentionally left blank.
    }
}
