<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist a validated, non-sensitive account language preference';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD preferred_locale VARCHAR(2) DEFAULT 'en' NOT NULL CHECK (preferred_locale IN ('en','de','fr'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP preferred_locale');
    }
}
