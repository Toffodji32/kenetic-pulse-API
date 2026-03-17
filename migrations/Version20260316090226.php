<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316090226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client ADD uuid VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE client ADD is_active BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE client ALTER registration_date TYPE VARCHAR(255)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C7440455D17F50A6 ON client (uuid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_C7440455D17F50A6');
        $this->addSql('ALTER TABLE client DROP uuid');
        $this->addSql('ALTER TABLE client DROP is_active');
        $this->addSql('ALTER TABLE client ALTER registration_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
    }
}
