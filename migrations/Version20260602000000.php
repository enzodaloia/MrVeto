<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création table specialite + jointure vet_profile_specialite, drop colonne JSON specialites, seed 28 spécialités prédéfinies';
    }

    public function up(Schema $schema): void
    {
        // Table specialite
        $this->addSql(<<<'SQL'
            CREATE TABLE specialite (
                id INT AUTO_INCREMENT NOT NULL,
                slug VARCHAR(100) NOT NULL,
                label VARCHAR(150) NOT NULL,
                is_predefined TINYINT(1) DEFAULT 0 NOT NULL,
                UNIQUE INDEX UNIQ_specialite_slug (slug),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Table de jointure vet_profile <-> specialite
        $this->addSql(<<<'SQL'
            CREATE TABLE vet_profile_specialite (
                vet_profile_id INT NOT NULL,
                specialite_id INT NOT NULL,
                INDEX IDX_vps_vp (vet_profile_id),
                INDEX IDX_vps_sp (specialite_id),
                PRIMARY KEY(vet_profile_id, specialite_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE vet_profile_specialite ADD CONSTRAINT FK_vps_vp FOREIGN KEY (vet_profile_id) REFERENCES vet_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vet_profile_specialite ADD CONSTRAINT FK_vps_sp FOREIGN KEY (specialite_id) REFERENCES specialite (id) ON DELETE CASCADE');

        // Drop ancienne colonne JSON
        $this->addSql('ALTER TABLE vet_profile DROP specialites');

        // Seed des 28 spécialités prédéfinies
        $seed = [
            ['medecine_generale',        'Médecine générale'],
            ['vaccination_identif',      'Vaccination et Identification'],
            ['pediatrie',                'Pédiatrie vétérinaire'],
            ['geriatrie',                'Gériatrie vétérinaire'],
            ['chir_convenance',          'Chirurgie de convenance (Stérilisations)'],
            ['chir_tissus_mous',         'Chirurgie des tissus mous'],
            ['chir_orthopedique',        'Chirurgie orthopédique et traumatologie'],
            ['neurochirurgie',           'Neurochirurgie'],
            ['gastro_enterologie',       'Gastro-entérologie'],
            ['cardiologie',              'Cardiologie'],
            ['pneumologie',              'Pneumologie'],
            ['endocrinologie',           'Endocrinologie'],
            ['urologie_nephrologie',     'Urologie et Néphrologie'],
            ['oncologie',                'Oncologie / Cancérologie'],
            ['hematologie_immunologie',  'Hématologie et Immunologie'],
            ['dermatologie',             'Dermatologie'],
            ['ophtalmologie',            'Ophtalmologie'],
            ['neurologie',               'Neurologie'],
            ['stomatologie_dentisterie', 'Stomatologie et Dentisterie'],
            ['imagerie_medicale',        'Imagerie médicale (Radiographie, Échographie, Scanner, IRM)'],
            ['biologie_medicale',        'Biologie médicale (Analyses de laboratoire)'],
            ['medecine_comportement',    'Médecine du comportement (Psychiatrie vétérinaire)'],
            ['physiotherapie',           'Physiothérapie et Rééducation fonctionnelle'],
            ['osteopathie',              'Ostéopathie vétérinaire'],
            ['nutrition',                'Nutrition et Diététique'],
            ['nac',                      'Médecine et chirurgie des NAC'],
            ['equine',                   'Médecine équine'],
            ['theriogenologie',          'Thériogénologie'],
        ];

        foreach ($seed as [$slug, $label]) {
            $slugEsc  = $this->connection->quote($slug);
            $labelEsc = $this->connection->quote($label);
            $this->addSql("INSERT INTO specialite (slug, label, is_predefined) VALUES ($slugEsc, $labelEsc, 1)");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vet_profile ADD specialites JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE vet_profile_specialite DROP FOREIGN KEY FK_vps_vp');
        $this->addSql('ALTER TABLE vet_profile_specialite DROP FOREIGN KEY FK_vps_sp');
        $this->addSql('DROP TABLE vet_profile_specialite');
        $this->addSql('DROP TABLE specialite');
    }
}
