<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240413121132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE analyse_result ADD COLUMN links_to_analyse CLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE analyse_result ADD COLUMN depth INTEGER NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__analyse_result AS SELECT id, url, links_nbr, links_found, images_nbr, total_time, analyse_en_cours FROM analyse_result');
        $this->addSql('DROP TABLE analyse_result');
        $this->addSql('CREATE TABLE analyse_result (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, url VARCHAR(512) NOT NULL, links_nbr INTEGER NOT NULL, links_found CLOB DEFAULT NULL --(DC2Type:array)
        , images_nbr INTEGER NOT NULL, total_time TIME NOT NULL, analyse_en_cours BOOLEAN DEFAULT NULL)');
        $this->addSql('INSERT INTO analyse_result (id, url, links_nbr, links_found, images_nbr, total_time, analyse_en_cours) SELECT id, url, links_nbr, links_found, images_nbr, total_time, analyse_en_cours FROM __temp__analyse_result');
        $this->addSql('DROP TABLE __temp__analyse_result');
    }
}
