<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324114916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la colonne status dans payment avec gestion des anciennes données';
    }

    public function up(Schema $schema): void
    {
        // 1️⃣ Ajouter la colonne avec valeur par défaut
        $this->addSql("ALTER TABLE payment ADD status VARCHAR(20) DEFAULT 'pending'");

        // 2️⃣ Mettre à jour les anciennes lignes
        $this->addSql("UPDATE payment SET status = 'pending' WHERE status IS NULL");

        // 3️⃣ Rendre la colonne obligatoire
        $this->addSql("ALTER TABLE payment ALTER COLUMN status SET NOT NULL");
    }

    public function down(Schema $schema): void
    {
        // Suppression propre si rollback
        $this->addSql("ALTER TABLE payment DROP COLUMN status");
    }
}