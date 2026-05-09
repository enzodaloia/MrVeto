<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260509150822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add slug to remaining entities safely.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSlugIfMissing('animal', 'UNIQ_6AAB231F989D9B62');
        $this->addSlugIfMissing('cabinet', 'UNIQ_4CED05B0989D9B62');
        $this->addSlugIfMissing('cabinet_user', 'UNIQ_A591D8E989D9B62');
        $this->addSlugIfMissing('day_of_work', 'UNIQ_EB39031989D9B62');
        $this->addSlugIfMissing('horaire', 'UNIQ_BBC83DB6989D9B62');
        $this->addSlugIfMissing('jour', 'UNIQ_DA17D9C5989D9B62');
        $this->addSlugIfMissing('reset_password_request', 'UNIQ_7CE748A989D9B62');
    }

    public function down(Schema $schema): void
    {
        $this->dropSlug('animal', 'UNIQ_6AAB231F989D9B62');
        $this->dropSlug('cabinet', 'UNIQ_4CED05B0989D9B62');
        $this->dropSlug('cabinet_user', 'UNIQ_A591D8E989D9B62');
        $this->dropSlug('day_of_work', 'UNIQ_EB39031989D9B62');
        $this->dropSlug('horaire', 'UNIQ_BBC83DB6989D9B62');
        $this->dropSlug('jour', 'UNIQ_DA17D9C5989D9B62');
        $this->dropSlug('reset_password_request', 'UNIQ_7CE748A989D9B62');
    }

    private function addSlugIfMissing(string $table, string $indexName): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist([$table])) {
            return;
        }

        $cols = $this->columnNamesLower($table);
        if (!in_array('slug', $cols, true)) {
            $this->addSql(sprintf('ALTER TABLE `%s` ADD slug VARCHAR(255) DEFAULT NULL', $table));
        }

        // Generate UUID for existing rows where slug is empty
        $this->addSql(sprintf(
            "UPDATE `%s` SET slug = UUID() WHERE slug IS NULL OR slug = ''",
            $table
        ));

        // Enforce NOT NULL
        $this->addSql(sprintf('ALTER TABLE `%s` MODIFY slug VARCHAR(255) NOT NULL', $table));

        // Create unique index if it doesn't exist
        if (!$this->indexExists($table, $indexName)) {
            $this->addSql(sprintf('CREATE UNIQUE INDEX %s ON `%s` (slug)', $indexName, $table));
        }
    }

    private function dropSlug(string $table, string $indexName): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist([$table])) {
            return;
        }

        if ($this->indexExists($table, $indexName)) {
            $this->addSql(sprintf('DROP INDEX %s ON `%s`', $indexName, $table));
        }

        if (in_array('slug', $this->columnNamesLower($table), true)) {
            $this->addSql(sprintf('ALTER TABLE `%s` DROP slug', $table));
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
