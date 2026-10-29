<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store disabled sandbox switches and sanitized operation health without secrets or searches';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE carrier_sandboxes (code VARCHAR(8) PRIMARY KEY REFERENCES carriers(code), enabled BOOLEAN NOT NULL DEFAULT false, checked_at TIMESTAMPTZ, operation VARCHAR(24), result VARCHAR(40), configuration_hash VARCHAR(64))');
        $this->addSql("INSERT INTO carrier_sandboxes(code) VALUES ('DHL'),('DPD'),('GLS')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE carrier_sandboxes');
    }
}
