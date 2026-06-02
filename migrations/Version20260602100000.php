<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mise à jour des labels des spécialités prédéfinies';
    }

    public function up(Schema $schema): void
    {
        $labels = [
            'medecine_generale'        => 'Médecine générale',
            'vaccination_identif'      => 'Vaccination et Identification',
            'pediatrie'                => 'Pédiatrie vétérinaire',
            'geriatrie'                => 'Gériatrie vétérinaire',
            'chir_convenance'          => 'Chirurgie de convenance',
            'chir_tissus_mous'         => 'Chirurgie des tissus mous',
            'chir_orthopedique'        => 'Chirurgie orthopédique et traumatologie',
            'neurochirurgie'           => 'Neurochirurgie',
            'gastro_enterologie'       => 'Gastro-entérologie',
            'cardiologie'              => 'Cardiologie',
            'pneumologie'              => 'Pneumologie',
            'endocrinologie'           => 'Endocrinologie',
            'urologie_nephrologie'     => 'Urologie et Néphrologie',
            'oncologie'                => 'Oncologie / Cancérologie',
            'hematologie_immunologie'  => 'Hématologie et Immunologie',
            'dermatologie'             => 'Dermatologie',
            'ophtalmologie'            => 'Ophtalmologie',
            'neurologie'               => 'Neurologie',
            'stomatologie_dentisterie' => 'Stomatologie et Dentisterie',
            'imagerie_medicale'        => 'Imagerie médicale',
            'biologie_medicale'        => 'Biologie médicale',
            'medecine_comportement'    => 'Médecine du comportement',
            'physiotherapie'           => 'Physiothérapie et Rééducation fonctionnelle',
            'osteopathie'              => 'Ostéopathie vétérinaire',
            'nutrition'                => 'Nutrition et Diététique',
            'nac'                      => 'Médecine et chirurgie des NAC',
            'equine'                   => 'Médecine équine',
            'theriogenologie'          => 'Thériogénologie',
        ];

        foreach ($labels as $slug => $label) {
            $slugEsc  = $this->connection->quote($slug);
            $labelEsc = $this->connection->quote($label);
            $this->addSql("UPDATE specialite SET label = $labelEsc WHERE slug = $slugEsc");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SELECT 1'); // pas de rollback des labels
    }
}
