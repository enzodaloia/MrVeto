<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603182510 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make slug nullable across entities, add rendez_vous.compte_rendu_pdf_data, drop vet_profile.is_urgentiste.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        foreach (['animal', 'cabinet', 'cabinet_user', 'day_of_work', 'horaire', 'jour', 'reset_password_request', 'traitement', 'user'] as $table) {
            $this->makeSlugNullable($table);
        }

        $this->updateRendezVousUp();
        $this->dropColumnIfExists('vet_profile', 'is_urgentiste');
    }

    public function down(Schema $schema): void
    {
        foreach (['animal', 'cabinet', 'cabinet_user', 'day_of_work', 'horaire', 'jour', 'reset_password_request', 'traitement', 'user'] as $table) {
            $this->makeSlugNotNull($table);
        }

        $this->updateRendezVousDown();
        $this->addUrgentisteColumnIfMissing();
    }

    private function makeSlugNullable(string $table): void
    {
        if (!$this->tableHasColumn($table, 'slug')) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE `%s` CHANGE slug slug VARCHAR(255) DEFAULT NULL', $table));
    }

    private function makeSlugNotNull(string $table): void
    {
        if (!$this->tableHasColumn($table, 'slug')) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE `%s` CHANGE slug slug VARCHAR(255) NOT NULL', $table));
    }

    private function updateRendezVousUp(): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['rendez_vous'])) {
            return;
        }

        $cols = $this->columnNamesLower('rendez_vous');
        $parts = [];

        if (!in_array('compte_rendu_pdf_data', $cols, true)) {
            $parts[] = 'ADD compte_rendu_pdf_data LONGBLOB DEFAULT NULL';
        }
        if (in_array('slug', $cols, true)) {
            $parts[] = 'CHANGE slug slug VARCHAR(255) DEFAULT NULL';
        }

        if ($parts !== []) {
            $this->addSql('ALTER TABLE `rendez_vous` ' . implode(', ', $parts));
        }
    }

    private function updateRendezVousDown(): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['rendez_vous'])) {
            return;
        }

        $cols = $this->columnNamesLower('rendez_vous');
        $parts = [];

        if (in_array('compte_rendu_pdf_data', $cols, true)) {
            $parts[] = 'DROP compte_rendu_pdf_data';
        }
        if (in_array('slug', $cols, true)) {
            $parts[] = 'CHANGE slug slug VARCHAR(255) NOT NULL';
        }

        if ($parts !== []) {
            $this->addSql('ALTER TABLE `rendez_vous` ' . implode(', ', $parts));
        }
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (!$this->tableHasColumn($table, $column)) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE `%s` DROP `%s`', $table, $column));
    }

    private function addUrgentisteColumnIfMissing(): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['vet_profile'])) {
            return;
        }

        if ($this->tableHasColumn('vet_profile', 'is_urgentiste')) {
            return;
        }

        $this->addSql('ALTER TABLE vet_profile ADD is_urgentiste TINYINT DEFAULT 0 NOT NULL');
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (!$this->connection->createSchemaManager()->tablesExist([$table])) {
            return false;
        }

        return in_array(strtolower($column), $this->columnNamesLower($table), true);
    }

    /** @return list<string> */
    private function columnNamesLower(string $table): array
    {
        $cols = $this->connection->createSchemaManager()->listTableColumns($table);

        return array_values(array_map(static fn ($c) => strtolower($c->getName()), $cols));
    }
}
