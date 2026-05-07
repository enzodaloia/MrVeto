<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligns rendez_vous table with RendezVous entity (motif, remarque, action metadata).
 */
final class Version20260507210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rendez_vous columns missing from DB (motif, remarque, previous_date_heure, last_action_*).';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['rendez_vous'])) {
            return;
        }

        $cols = $this->columnNamesLower('rendez_vous');
        $parts = [];

        if (!in_array('motif', $cols, true)) {
            $parts[] = 'ADD motif VARCHAR(255) DEFAULT NULL';
        }
        if (!in_array('remarque', $cols, true)) {
            $parts[] = 'ADD remarque LONGTEXT DEFAULT NULL';
        }
        if (!in_array('previous_date_heure', $cols, true)) {
            $parts[] = 'ADD previous_date_heure DATETIME DEFAULT NULL';
        }
        if (!in_array('last_action_type', $cols, true)) {
            $parts[] = 'ADD last_action_type VARCHAR(20) DEFAULT NULL';
        }
        if (!in_array('last_action_by_role', $cols, true)) {
            $parts[] = 'ADD last_action_by_role VARCHAR(20) DEFAULT NULL';
        }
        if (!in_array('last_action_at', $cols, true)) {
            $parts[] = 'ADD last_action_at DATETIME DEFAULT NULL';
        }

        if ($parts !== []) {
            $this->addSql('ALTER TABLE `rendez_vous` ' . implode(', ', $parts));
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['rendez_vous'])) {
            return;
        }

        $cols = $this->columnNamesLower('rendez_vous');
        $drops = [];
        foreach (['last_action_at', 'last_action_by_role', 'last_action_type', 'previous_date_heure', 'remarque', 'motif'] as $c) {
            if (in_array($c, $cols, true)) {
                $drops[] = 'DROP ' . $c;
            }
        }
        if ($drops !== []) {
            $this->addSql('ALTER TABLE `rendez_vous` ' . implode(', ', $drops));
        }
    }

    /** @return list<string> */
    private function columnNamesLower(string $table): array
    {
        $cols = $this->connection->createSchemaManager()->listTableColumns($table);

        return array_values(array_map(static fn ($c) => strtolower($c->getName()), $cols));
    }
}
