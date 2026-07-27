<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727162155 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique constraint on user.email, fix corrupted gym slugs';
    }

    public function up(Schema $schema): void
    {
        // ── User email unique constraint ──
        // Drop the manually-added constraint
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT IF EXISTS uniq_8d93d649e7927c74');
        // Drop the unique INDEX (created by previous partial migrations)
        $this->addSql('DROP INDEX IF EXISTS uniq_identifier_email');
        // Now create the proper UNIQUE CONSTRAINT (Doctrine will use it for schema validation)
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT UNIQ_IDENTIFIER_EMAIL UNIQUE (email)');

        // ── Fix corrupted gym slugs ──
        $this->addSql("UPDATE gym SET slug = 'mike-gym'          WHERE id = 4 AND slug = 'ike-ym'");
        $this->addSql("UPDATE gym SET slug = 'zone-gym'          WHERE id = 5 AND slug = 'zonzgym'");
        $this->addSql("UPDATE gym SET slug = 'constance-salle'   WHERE id = 6 AND slug = '-2'");
        $this->addSql("UPDATE gym SET slug = 'ida'               WHERE id = 7 AND slug = '-3'");
        $this->addSql("UPDATE gym SET slug = 'arya'              WHERE id = 2 AND (slug IS NULL OR slug = '')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT IF EXISTS UNIQ_IDENTIFIER_EMAIL');
    }
}
