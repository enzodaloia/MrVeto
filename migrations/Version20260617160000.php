<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma MrVeto idempotent : crée les tables manquantes et ajoute les colonnes absentes.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->ensureUser();
        $this->ensureSpecialite();
        $this->ensureCabinet();
        $this->ensureJour();
        $this->ensureAnimal();
        $this->ensureVetProfile();
        $this->ensureCabinetUser();
        $this->ensureDayOfWork();
        $this->ensureHoraire();
        $this->ensureResetPasswordRequest();
        $this->ensureTraitement();
        $this->ensureRendezVous();
        $this->ensureVetProfileSpecialite();
        $this->ensureMessengerMessages();
        $this->seedSpecialites();
    }

    public function down(Schema $schema): void
    {
        $this->write('Migration baseline idempotente : rollback non supporté.');
    }

    private function ensureUser(): void
    {
        $this->ensureTable('user', <<<'SQL'
            CREATE TABLE user (
                id INT AUTO_INCREMENT NOT NULL,
                email VARCHAR(180) NOT NULL,
                roles JSON NOT NULL,
                password VARCHAR(255) NOT NULL,
                is_verified TINYINT(1) NOT NULL,
                nom VARCHAR(255) DEFAULT NULL,
                prenom VARCHAR(255) DEFAULT NULL,
                datenaissance DATETIME DEFAULT NULL,
                adresse VARCHAR(255) DEFAULT NULL,
                telephone VARCHAR(255) DEFAULT NULL,
                ville VARCHAR(255) DEFAULT NULL,
                codepostal VARCHAR(255) DEFAULT NULL,
                siret VARCHAR(255) DEFAULT NULL,
                adressecabinet VARCHAR(255) DEFAULT NULL,
                token VARCHAR(255) DEFAULT NULL,
                img VARCHAR(255) DEFAULT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                longitude VARCHAR(255) DEFAULT NULL,
                latitude VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                is_archived TINYINT(1) NOT NULL DEFAULT 0,
                archived_at DATETIME DEFAULT NULL,
                UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email),
                UNIQUE INDEX UNIQ_8D93D649989D9B62 (slug),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL, [
            'email' => 'VARCHAR(180) NOT NULL',
            'roles' => 'JSON NOT NULL',
            'password' => 'VARCHAR(255) NOT NULL',
            'is_verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'nom' => 'VARCHAR(255) DEFAULT NULL',
            'prenom' => 'VARCHAR(255) DEFAULT NULL',
            'datenaissance' => 'DATETIME DEFAULT NULL',
            'adresse' => 'VARCHAR(255) DEFAULT NULL',
            'telephone' => 'VARCHAR(255) DEFAULT NULL',
            'ville' => 'VARCHAR(255) DEFAULT NULL',
            'codepostal' => 'VARCHAR(255) DEFAULT NULL',
            'siret' => 'VARCHAR(255) DEFAULT NULL',
            'adressecabinet' => 'VARCHAR(255) DEFAULT NULL',
            'token' => 'VARCHAR(255) DEFAULT NULL',
            'img' => 'VARCHAR(255) DEFAULT NULL',
            'slug' => 'VARCHAR(255) DEFAULT NULL',
            'longitude' => 'VARCHAR(255) DEFAULT NULL',
            'latitude' => 'VARCHAR(255) DEFAULT NULL',
            'created_at' => 'DATETIME NOT NULL',
            'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'archived_at' => 'DATETIME DEFAULT NULL',
        ]);
    }

    private function ensureSpecialite(): void
    {
        $this->ensureTable('specialite', <<<'SQL'
            CREATE TABLE specialite (
                id INT AUTO_INCREMENT NOT NULL,
                slug VARCHAR(100) NOT NULL,
                label VARCHAR(150) NOT NULL,
                is_predefined TINYINT(1) DEFAULT 0 NOT NULL,
                UNIQUE INDEX UNIQ_E7D6FCC1989D9B62 (slug),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL, [
            'slug' => 'VARCHAR(100) NOT NULL',
            'label' => 'VARCHAR(150) NOT NULL',
            'is_predefined' => 'TINYINT(1) DEFAULT 0 NOT NULL',
        ]);
    }

    private function ensureCabinet(): void
    {
        $this->ensureTable('cabinet', <<<'SQL'
            CREATE TABLE cabinet (
                id INT AUTO_INCREMENT NOT NULL,
                nom VARCHAR(255) NOT NULL,
                adresse VARCHAR(255) DEFAULT NULL,
                ville VARCHAR(255) DEFAULT NULL,
                code_postal VARCHAR(20) DEFAULT NULL,
                telephone VARCHAR(30) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                UNIQUE INDEX UNIQ_4CED05B0989D9B62 (slug),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL, [
            'nom' => 'VARCHAR(255) NOT NULL',
            'adresse' => 'VARCHAR(255) DEFAULT NULL',
            'ville' => 'VARCHAR(255) DEFAULT NULL',
            'code_postal' => 'VARCHAR(20) DEFAULT NULL',
            'telephone' => 'VARCHAR(30) DEFAULT NULL',
            'created_at' => 'DATETIME NOT NULL',
            'slug' => 'VARCHAR(255) DEFAULT NULL',
        ]);
    }

    private function ensureJour(): void
    {
        $this->ensureTable('jour', <<<'SQL'
            CREATE TABLE jour (
                id INT AUTO_INCREMENT NOT NULL,
                libelle VARCHAR(50) NOT NULL,
                ordre INT NOT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                UNIQUE INDEX UNIQ_DA17D9C5989D9B62 (slug),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL, [
            'libelle' => 'VARCHAR(50) NOT NULL',
            'ordre' => 'INT NOT NULL',
            'slug' => 'VARCHAR(255) DEFAULT NULL',
        ]);
    }

    private function ensureAnimal(): void
    {
        $this->ensureTable('animal', <<<'SQL'
            CREATE TABLE animal (
                id INT AUTO_INCREMENT NOT NULL,
                nom VARCHAR(255) NOT NULL,
                espece VARCHAR(255) DEFAULT NULL,
                race VARCHAR(255) DEFAULT NULL,
                age VARCHAR(255) DEFAULT NULL,
                vaccin_ajour TINYINT(1) DEFAULT NULL,
                prochain_vaccin DATE DEFAULT NULL,
                poids VARCHAR(50) DEFAULT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                proprietaire_id INT NOT NULL,
                UNIQUE INDEX UNIQ_6AAB231F989D9B62 (slug),
                INDEX IDX_6AAB231F76C50E4A (proprietaire_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_6AAB231F76C50E4A FOREIGN KEY (proprietaire_id) REFERENCES user (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL, [
            'nom' => 'VARCHAR(255) NOT NULL',
            'espece' => 'VARCHAR(255) DEFAULT NULL',
            'race' => 'VARCHAR(255) DEFAULT NULL',
            'age' => 'VARCHAR(255) DEFAULT NULL',
            'vaccin_ajour' => 'TINYINT(1) DEFAULT NULL',
            'prochain_vaccin' => 'DATE DEFAULT NULL',
            'poids' => 'VARCHAR(50) DEFAULT NULL',
            'slug' => 'VARCHAR(255) DEFAULT NULL',
            'proprietaire_id' => 'INT NOT NULL',
        ]);
    }

    private function ensureVetProfile(): void
    {
        $this->ensureTable('vet_profile', <<<'SQL'
            CREATE TABLE vet_profile (
                id INT AUTO_INCREMENT NOT NULL,
                a_propos LONGTEXT DEFAULT NULL,
                moyens_paiement JSON DEFAULT NULL,
                animaux_acceptes JSON DEFAULT NULL,
                duree_consultation VARCHAR(50) DEFAULT NULL,
                is_urgentiste TINYINT(1) DEFAULT 0 NOT NULL,
                langues JSON DEFAULT NULL,
                user_id INT NOT NULL,
                UNIQUE INDEX UNIQ_77ACE0AAA76ED395 (user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_77ACE0AAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL, [
            'a_propos' => 'LONGTEXT DEFAULT NULL',
            'moyens_paiement' => 'JSON DEFAULT NULL',
            'animaux_acceptes' => 'JSON DEFAULT NULL',
            'duree_consultation' => 'VARCHAR(50) DEFAULT NULL',
            'is_urgentiste' => 'TINYINT(1) DEFAULT 0 NOT NULL',
            'langues' => 'JSON DEFAULT NULL',
            'user_id' => 'INT NOT NULL',
        ]);
    }

    private function ensureCabinetUser(): void
    {
        $this->ensureTable('cabinet_user', <<<'SQL'
            CREATE TABLE cabinet_user (
                id INT AUTO_INCREMENT NOT NULL,
                role_in_cabinet VARCHAR(32) NOT NULL,
                created_at DATETIME NOT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                cabinet_id INT NOT NULL,
                user_id INT NOT NULL,
                UNIQUE INDEX UNIQ_A591D8E989D9B62 (slug),
                INDEX IDX_A591D8ED351EC (cabinet_id),
                INDEX IDX_A591D8EA76ED395 (user_id),
                UNIQUE INDEX uniq_cabinet_user_pair (cabinet_id, user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_A591D8ED351EC FOREIGN KEY (cabinet_id) REFERENCES cabinet (id) ON DELETE CASCADE,
                CONSTRAINT FK_A591D8EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL, [
            'role_in_cabinet' => 'VARCHAR(32) NOT NULL',
            'created_at' => 'DATETIME NOT NULL',
            'slug' => 'VARCHAR(255) DEFAULT NULL',
            'cabinet_id' => 'INT NOT NULL',
            'user_id' => 'INT NOT NULL',
        ]);
    }

    private function ensureDayOfWork(): void
    {
        $this->ensureTable('day_of_work', <<<'SQL'
            CREATE TABLE day_of_work (
                id INT AUTO_INCREMENT NOT NULL,
                is_working TINYINT(1) DEFAULT 1 NOT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                user_id INT NOT NULL,
                jour_id INT NOT NULL,
                UNIQUE INDEX UNIQ_EB39031989D9B62 (slug),
                INDEX IDX_EB39031A76ED395 (user_id),
                INDEX IDX_EB39031220C6AD0 (jour_id),
                UNIQUE INDEX uniq_user_jour (user_id, jour_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_EB39031A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE,
                CONSTRAINT FK_EB39031220C6AD0 FOREIGN KEY (jour_id) REFERENCES jour (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL, [
            'is_working' => 'TINYINT(1) DEFAULT 1 NOT NULL',
            'slug' => 'VARCHAR(255) DEFAULT NULL',
            'user_id' => 'INT NOT NULL',
            'jour_id' => 'INT NOT NULL',
        ]);
    }

    private function ensureHoraire(): void
    {
        $this->ensureTable('horaire', <<<'SQL'
            CREATE TABLE horaire (
                id INT AUTO_INCREMENT NOT NULL,
                morning_start TIME DEFAULT NULL,
                morning_end TIME DEFAULT NULL,
                afternoon_start TIME DEFAULT NULL,
                afternoon_end TIME DEFAULT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                day_of_work_id INT NOT NULL,
                UNIQUE INDEX UNIQ_BBC83DB6989D9B62 (slug),
                INDEX IDX_BBC83DB660C122B5 (day_of_work_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_BBC83DB660C122B5 FOREIGN KEY (day_of_work_id) REFERENCES day_of_work (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL, [
            'morning_start' => 'TIME DEFAULT NULL',
            'morning_end' => 'TIME DEFAULT NULL',
            'afternoon_start' => 'TIME DEFAULT NULL',
            'afternoon_end' => 'TIME DEFAULT NULL',
            'slug' => 'VARCHAR(255) DEFAULT NULL',
            'day_of_work_id' => 'INT NOT NULL',
        ]);
    }

    private function ensureResetPasswordRequest(): void
    {
        $this->ensureTable('reset_password_request', <<<'SQL'
            CREATE TABLE reset_password_request (
                id INT AUTO_INCREMENT NOT NULL,
                selector VARCHAR(20) NOT NULL,
                hashed_token VARCHAR(100) NOT NULL,
                requested_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                user_id INT NOT NULL,
                UNIQUE INDEX UNIQ_7CE748A989D9B62 (slug),
                INDEX IDX_7CE748AA76ED395 (user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL, [
            'selector' => 'VARCHAR(20) NOT NULL',
            'hashed_token' => 'VARCHAR(100) NOT NULL',
            'requested_at' => 'DATETIME NOT NULL',
            'expires_at' => 'DATETIME NOT NULL',
            'slug' => 'VARCHAR(255) DEFAULT NULL',
            'user_id' => 'INT NOT NULL',
        ]);
    }

    private function ensureTraitement(): void
    {
        $this->ensureTable('traitement', <<<'SQL'
            CREATE TABLE traitement (
                id INT AUTO_INCREMENT NOT NULL,
                libelle VARCHAR(255) NOT NULL,
                posologie VARCHAR(255) DEFAULT NULL,
                date_debut DATE NOT NULL,
                date_fin DATE DEFAULT NULL,
                created_at DATETIME NOT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                animal_id INT NOT NULL,
                veterinaire_id INT NOT NULL,
                UNIQUE INDEX UNIQ_2A356D27989D9B62 (slug),
                INDEX IDX_2A356D278E962C16 (animal_id),
                INDEX IDX_2A356D275C80924 (veterinaire_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_2A356D278E962C16 FOREIGN KEY (animal_id) REFERENCES animal (id) ON DELETE CASCADE,
                CONSTRAINT FK_2A356D275C80924 FOREIGN KEY (veterinaire_id) REFERENCES user (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL, [
            'libelle' => 'VARCHAR(255) NOT NULL',
            'posologie' => 'VARCHAR(255) DEFAULT NULL',
            'date_debut' => 'DATE NOT NULL',
            'date_fin' => 'DATE DEFAULT NULL',
            'created_at' => 'DATETIME NOT NULL',
            'slug' => 'VARCHAR(255) DEFAULT NULL',
            'animal_id' => 'INT NOT NULL',
            'veterinaire_id' => 'INT NOT NULL',
        ]);
    }

    private function ensureRendezVous(): void
    {
        $this->ensureTable('rendez_vous', <<<'SQL'
            CREATE TABLE rendez_vous (
                id INT AUTO_INCREMENT NOT NULL,
                date_heure DATETIME NOT NULL,
                statut VARCHAR(50) NOT NULL,
                motif VARCHAR(255) DEFAULT NULL,
                remarque LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                previous_date_heure DATETIME DEFAULT NULL,
                last_action_type VARCHAR(20) DEFAULT NULL,
                last_action_by_role VARCHAR(20) DEFAULT NULL,
                last_action_at DATETIME DEFAULT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                compte_rendu LONGTEXT DEFAULT NULL,
                compte_rendu_pdf VARCHAR(255) DEFAULT NULL,
                compte_rendu_pdf_data LONGBLOB DEFAULT NULL,
                is_compte_rendu_validated TINYINT(1) NOT NULL DEFAULT 0,
                client_id INT NOT NULL,
                veterinaire_id INT NOT NULL,
                animal_id INT DEFAULT NULL,
                UNIQUE INDEX UNIQ_65E8AA0A989D9B62 (slug),
                INDEX IDX_65E8AA0A19EB6921 (client_id),
                INDEX IDX_65E8AA0A5C80924 (veterinaire_id),
                INDEX IDX_65E8AA0A8E962C16 (animal_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_65E8AA0A19EB6921 FOREIGN KEY (client_id) REFERENCES user (id) ON DELETE CASCADE,
                CONSTRAINT FK_65E8AA0A5C80924 FOREIGN KEY (veterinaire_id) REFERENCES user (id) ON DELETE CASCADE,
                CONSTRAINT FK_65E8AA0A8E962C16 FOREIGN KEY (animal_id) REFERENCES animal (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL, [
            'date_heure' => 'DATETIME NOT NULL',
            'statut' => 'VARCHAR(50) NOT NULL',
            'motif' => 'VARCHAR(255) DEFAULT NULL',
            'remarque' => 'LONGTEXT DEFAULT NULL',
            'created_at' => 'DATETIME NOT NULL',
            'previous_date_heure' => 'DATETIME DEFAULT NULL',
            'last_action_type' => 'VARCHAR(20) DEFAULT NULL',
            'last_action_by_role' => 'VARCHAR(20) DEFAULT NULL',
            'last_action_at' => 'DATETIME DEFAULT NULL',
            'slug' => 'VARCHAR(255) DEFAULT NULL',
            'compte_rendu' => 'LONGTEXT DEFAULT NULL',
            'compte_rendu_pdf' => 'VARCHAR(255) DEFAULT NULL',
            'compte_rendu_pdf_data' => 'LONGBLOB DEFAULT NULL',
            'is_compte_rendu_validated' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'client_id' => 'INT NOT NULL',
            'veterinaire_id' => 'INT NOT NULL',
            'animal_id' => 'INT DEFAULT NULL',
        ]);
    }

    private function ensureVetProfileSpecialite(): void
    {
        if ($this->tableExists('vet_profile_specialite')) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE vet_profile_specialite (
                vet_profile_id INT NOT NULL,
                specialite_id INT NOT NULL,
                INDEX IDX_4A80A7533B001DE (vet_profile_id),
                INDEX IDX_4A80A752195E0F0 (specialite_id),
                PRIMARY KEY(vet_profile_id, specialite_id),
                CONSTRAINT FK_4A80A7533B001DE FOREIGN KEY (vet_profile_id) REFERENCES vet_profile (id) ON DELETE CASCADE,
                CONSTRAINT FK_4A80A752195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);
    }

    private function ensureMessengerMessages(): void
    {
        if ($this->tableExists('messenger_messages')) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
                id BIGINT AUTO_INCREMENT NOT NULL,
                body LONGTEXT NOT NULL,
                headers LONGTEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL,
                available_at DATETIME NOT NULL,
                delivered_at DATETIME DEFAULT NULL,
                INDEX IDX_75EA56E0FB7336F0 (queue_name),
                INDEX IDX_75EA56E0E3BD61CE (available_at),
                INDEX IDX_75EA56E016BA31DB (delivered_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);
    }

    /**
     * @param array<string, string> $columns
     */
    private function ensureTable(string $table, string $createSql, array $columns): void
    {
        if (!$this->tableExists($table)) {
            $this->addSql($createSql);

            return;
        }

        $this->ensureColumns($table, $columns);
    }

    /**
     * @param array<string, string> $columns
     */
    private function ensureColumns(string $table, array $columns): void
    {
        $parts = [];

        foreach ($columns as $name => $definition) {
            if (!$this->hasColumn($table, $name)) {
                $parts[] = sprintf('ADD `%s` %s', $name, $definition);
            }
        }

        if ($parts === []) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE `%s` %s', $table, implode(', ', $parts)));
    }

    private function tableExists(string $table): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([$table]);
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        return in_array(strtolower($column), $this->columnNamesLower($table), true);
    }

    /** @return list<string> */
    private function columnNamesLower(string $table): array
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns($table);

        return array_values(array_map(static fn ($column) => strtolower($column->getName()), $columns));
    }

    private function seedSpecialites(): void
    {
        if (!$this->tableExists('specialite')) {
            return;
        }

        $seed = [
            ['medecine_generale', 'Médecine générale'],
            ['vaccination_identif', 'Vaccination et Identification'],
            ['pediatrie', 'Pédiatrie vétérinaire'],
            ['geriatrie', 'Gériatrie vétérinaire'],
            ['chir_convenance', 'Chirurgie de convenance'],
            ['chir_tissus_mous', 'Chirurgie des tissus mous'],
            ['chir_orthopedique', 'Chirurgie orthopédique et traumatologie'],
            ['neurochirurgie', 'Neurochirurgie'],
            ['gastro_enterologie', 'Gastro-entérologie'],
            ['cardiologie', 'Cardiologie'],
            ['pneumologie', 'Pneumologie'],
            ['endocrinologie', 'Endocrinologie'],
            ['urologie_nephrologie', 'Urologie et Néphrologie'],
            ['oncologie', 'Oncologie / Cancérologie'],
            ['hematologie_immunologie', 'Hématologie et Immunologie'],
            ['dermatologie', 'Dermatologie'],
            ['ophtalmologie', 'Ophtalmologie'],
            ['neurologie', 'Neurologie'],
            ['stomatologie_dentisterie', 'Stomatologie et Dentisterie'],
            ['imagerie_medicale', 'Imagerie médicale'],
            ['biologie_medicale', 'Biologie médicale'],
            ['medecine_comportement', 'Médecine du comportement'],
            ['physiotherapie', 'Physiothérapie et Rééducation fonctionnelle'],
            ['osteopathie', 'Ostéopathie vétérinaire'],
            ['nutrition', 'Nutrition et Diététique'],
            ['nac', 'Médecine et chirurgie des NAC'],
            ['equine', 'Médecine équine'],
            ['theriogenologie', 'Thériogénologie'],
        ];

        foreach ($seed as [$slug, $label]) {
            if ($this->specialiteExists($slug)) {
                continue;
            }

            $slugEsc = $this->connection->quote($slug);
            $labelEsc = $this->connection->quote($label);
            $this->addSql("INSERT INTO specialite (slug, label, is_predefined) VALUES ($slugEsc, $labelEsc, 1)");
        }
    }

    private function specialiteExists(string $slug): bool
    {
        $result = $this->connection->fetchOne(
            'SELECT 1 FROM specialite WHERE slug = ? LIMIT 1',
            [$slug]
        );

        return $result !== false;
    }
}
