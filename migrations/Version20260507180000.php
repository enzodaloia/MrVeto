<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds user.slug (and related columns) if missing — fixes login when entity has slug but DB does not.
 */
final class Version20260507180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.slug, is_archived, archived_at when missing (backfill slugs for existing rows).';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['user'])) {
            return;
        }

        $cols = $this->columnNamesLower('user');

        if (!in_array('slug', $cols, true)) {
            $this->addSql('ALTER TABLE `user` ADD slug VARCHAR(255) DEFAULT NULL');
        }

        $this->addSql("UPDATE `user` SET slug = CONCAT('user-', id) WHERE slug IS NULL OR slug = ''");

        $this->addSql('ALTER TABLE `user` MODIFY slug VARCHAR(255) NOT NULL');

        if (!$this->indexExists('user', 'UNIQ_8D93D649989D9B62')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649989D9B62 ON `user` (slug)');
        }

        if (!in_array('is_archived', $cols, true)) {
            $this->addSql('ALTER TABLE `user` ADD is_archived TINYINT NOT NULL DEFAULT 0');
        }

        if (!in_array('archived_at', $cols, true)) {
            $this->addSql('ALTER TABLE `user` ADD archived_at DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['user'])) {
            return;
        }

        if ($this->indexExists('user', 'UNIQ_8D93D649989D9B62')) {
            $this->addSql('DROP INDEX UNIQ_8D93D649989D9B62 ON `user`');
        }

        $cols = $this->columnNamesLower('user');
        if (in_array('slug', $cols, true)) {
            $this->addSql('ALTER TABLE `user` DROP slug');
        }
        if (in_array('archived_at', $cols, true)) {
            $this->addSql('ALTER TABLE `user` DROP archived_at');
        }
        if (in_array('is_archived', $cols, true)) {
            $this->addSql('ALTER TABLE `user` DROP is_archived');
        }
    }

    /** @return list<string> */
    private function columnNamesLower(string $table): array
    {
        $cols = $this->connection->createSchemaManager()->listTableColumns($table);

        return array_values(array_map(static fn ($c) => strtolower($c->getName()), $cols));
    }

    private function indexExists(string $table, string $name): bool
    {
        foreach ($this->connection->createSchemaManager()->listTableIndexes($table) as $idx) {
            if (strtolower($idx->getName()) === strtolower($name)) {
                return true;
            }
        }

        return false;
    }
}
