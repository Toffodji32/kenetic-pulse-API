<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add description field to Gym, change logo to TEXT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gym ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE gym ALTER COLUMN logo TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gym DROP description');
        $this->addSql('ALTER TABLE gym ALTER COLUMN logo TYPE VARCHAR(255)');
    }
}
