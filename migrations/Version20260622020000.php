<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add plan_type column to gym_subscription';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE gym_subscription ADD plan_type VARCHAR(20) DEFAULT 'premium' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gym_subscription DROP plan_type');
    }
}
