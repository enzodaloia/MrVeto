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
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // animal (the column might exist but have empty values)
        $this->addSql('UPDATE animal SET slug = UUID() WHERE slug IS NULL OR slug = \'\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6AAB231F989D9B62 ON animal (slug)');

        $this->addSql('ALTER TABLE cabinet ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE cabinet SET slug = UUID() WHERE slug IS NULL OR slug = \'\'');
        $this->addSql('ALTER TABLE cabinet MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4CED05B0989D9B62 ON cabinet (slug)');

        $this->addSql('ALTER TABLE cabinet_user ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE cabinet_user SET slug = UUID() WHERE slug IS NULL OR slug = \'\'');
        $this->addSql('ALTER TABLE cabinet_user MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A591D8E989D9B62 ON cabinet_user (slug)');

        $this->addSql('ALTER TABLE day_of_work ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE day_of_work SET slug = UUID() WHERE slug IS NULL OR slug = \'\'');
        $this->addSql('ALTER TABLE day_of_work MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EB39031989D9B62 ON day_of_work (slug)');

        $this->addSql('ALTER TABLE horaire ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE horaire SET slug = UUID() WHERE slug IS NULL OR slug = \'\'');
        $this->addSql('ALTER TABLE horaire MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BBC83DB6989D9B62 ON horaire (slug)');

        $this->addSql('ALTER TABLE jour ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE jour SET slug = UUID() WHERE slug IS NULL OR slug = \'\'');
        $this->addSql('ALTER TABLE jour MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DA17D9C5989D9B62 ON jour (slug)');

        $this->addSql('ALTER TABLE reset_password_request ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE reset_password_request SET slug = UUID() WHERE slug IS NULL OR slug = \'\'');
        $this->addSql('ALTER TABLE reset_password_request MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7CE748A989D9B62 ON reset_password_request (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_6AAB231F989D9B62 ON animal');
        $this->addSql('DROP INDEX UNIQ_4CED05B0989D9B62 ON cabinet');
        $this->addSql('ALTER TABLE cabinet DROP slug');
        $this->addSql('DROP INDEX UNIQ_A591D8E989D9B62 ON cabinet_user');
        $this->addSql('ALTER TABLE cabinet_user DROP slug');
        $this->addSql('DROP INDEX UNIQ_EB39031989D9B62 ON day_of_work');
        $this->addSql('ALTER TABLE day_of_work DROP slug');
        $this->addSql('DROP INDEX UNIQ_BBC83DB6989D9B62 ON horaire');
        $this->addSql('ALTER TABLE horaire DROP slug');
        $this->addSql('DROP INDEX UNIQ_DA17D9C5989D9B62 ON jour');
        $this->addSql('ALTER TABLE jour DROP slug');
        $this->addSql('DROP INDEX UNIQ_7CE748A989D9B62 ON reset_password_request');
        $this->addSql('ALTER TABLE reset_password_request DROP slug');
    }
}
