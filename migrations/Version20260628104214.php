<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628104214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add gym_id to subscription_type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription_type ADD gym_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription_type ADD CONSTRAINT FK_BBE24737BD2F03 FOREIGN KEY (gym_id) REFERENCES gym (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_BBE24737BD2F03 ON subscription_type (gym_id)');

        // Assign existing types to the first gym
        $firstGym = $this->connection->fetchOne('SELECT id FROM gym ORDER BY id ASC LIMIT 1');
        if ($firstGym) {
            $this->addSql("UPDATE subscription_type SET gym_id = $firstGym WHERE gym_id IS NULL");
        }

        $this->addSql('ALTER TABLE subscription_type ALTER gym_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription_type DROP CONSTRAINT FK_BBE24737BD2F03');
        $this->addSql('DROP INDEX IDX_BBE24737BD2F03');
        $this->addSql('ALTER TABLE subscription_type DROP gym_id');
    }
}
