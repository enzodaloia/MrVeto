<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create vet_profile table (professional vet info).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE vet_profile (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                a_propos LONGTEXT DEFAULT NULL,
                specialites JSON DEFAULT NULL,
                moyens_paiement JSON DEFAULT NULL,
                animaux_acceptes JSON DEFAULT NULL,
                duree_consultation VARCHAR(50) DEFAULT NULL,
                langues JSON DEFAULT NULL,
                UNIQUE INDEX UNIQ_VET_PROFILE_USER (user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE vet_profile
                ADD CONSTRAINT FK_VET_PROFILE_USER
                FOREIGN KEY (user_id) REFERENCES `user` (id)
                ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vet_profile DROP FOREIGN KEY FK_VET_PROFILE_USER');
        $this->addSql('DROP TABLE vet_profile');
    }
}
