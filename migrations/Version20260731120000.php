<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix plan_type default: gyms en trial passent a basic';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE gym_subscription ALTER plan_type SET DEFAULT 'basic'");
        $this->addSql("UPDATE gym_subscription SET plan_type = 'basic' WHERE plan_type = 'premium' AND status = 'trial'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE gym_subscription ALTER plan_type SET DEFAULT 'premium'");
    }
}
