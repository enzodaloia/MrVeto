<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260504075923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE horaire DROP FOREIGN KEY `FK_BBC83DB660C122B5`');
        $this->addSql('ALTER TABLE horaire ADD CONSTRAINT FK_BBC83DB660C122B5 FOREIGN KEY (day_of_work_id) REFERENCES day_of_work (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY `FK_65E8AA0A19EB6921`');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY `FK_65E8AA0A5C80924`');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY `FK_65E8AA0A8E962C16`');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A19EB6921 FOREIGN KEY (client_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A5C80924 FOREIGN KEY (veterinaire_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A8E962C16 FOREIGN KEY (animal_id) REFERENCES animal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user DROP numero_ordre_veterinaire, DROP is_admin_validated');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE horaire DROP FOREIGN KEY FK_BBC83DB660C122B5');
        $this->addSql('ALTER TABLE horaire ADD CONSTRAINT `FK_BBC83DB660C122B5` FOREIGN KEY (day_of_work_id) REFERENCES day_of_work (id)');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A19EB6921');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A5C80924');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A8E962C16');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT `FK_65E8AA0A19EB6921` FOREIGN KEY (client_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT `FK_65E8AA0A5C80924` FOREIGN KEY (veterinaire_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT `FK_65E8AA0A8E962C16` FOREIGN KEY (animal_id) REFERENCES animal (id)');
        $this->addSql('ALTER TABLE user ADD numero_ordre_veterinaire VARCHAR(255) DEFAULT NULL, ADD is_admin_validated TINYINT NOT NULL');
    }
}
