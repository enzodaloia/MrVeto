<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds slug on traitement and rendez_vous when missing (entity mapping already requires them).
 */
final class Version20260507200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add slug column + unique index on traitement and rendez_vous if missing.';
    }

    public function up(Schema $schema): void
    {
        $this->addSlugWithBackfill('traitement', 'UNIQ_2A356D27989D9B62', 'traitement-');
        $this->addSlugWithBackfill('rendez_vous', 'UNIQ_65E8AA0A989D9B62', 'rdv-');
    }

    public function down(Schema $schema): void
    {
        $this->dropSlug('traitement', 'UNIQ_2A356D27989D9B62');
        $this->dropSlug('rendez_vous', 'UNIQ_65E8AA0A989D9B62');
    }

    private function addSlugWithBackfill(string $table, string $indexName, string $prefix): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist([$table])) {
            return;
        }

        $cols = $this->columnNamesLower($table);
        if (!in_array('slug', $cols, true)) {
            $this->addSql(sprintf('ALTER TABLE `%s` ADD slug VARCHAR(255) DEFAULT NULL', $table));
        }

        $this->addSql(sprintf(
            "UPDATE `%s` SET slug = CONCAT('%s', id) WHERE slug IS NULL OR slug = ''",
            $table,
            $prefix
        ));

        $this->addSql(sprintf('ALTER TABLE `%s` MODIFY slug VARCHAR(255) NOT NULL', $table));

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
