<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change product.image from varchar(255) to TEXT for base64 storage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ALTER COLUMN image TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ALTER COLUMN image TYPE VARCHAR(255)');
    }
}
