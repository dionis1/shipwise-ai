<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Constrain persisted offer money and index immutable price snapshots';
    }

    public function up(Schema $schema): void
    {
        // Disable the update trigger only for this one-time additive backfill.
        $this->addSql('ALTER TABLE shipping_quote_offers DISABLE TRIGGER offer_immutable');
        $this->addSql('ALTER TABLE shipping_quote_offers ADD selling_minor BIGINT, ADD currency VARCHAR(3)');
        $this->addSql("UPDATE shipping_quote_offers SET selling_minor=(snapshot #>> '{price,totalMinor}')::bigint,currency=snapshot #>> '{price,currency}'");
        $this->addSql('ALTER TABLE shipping_quote_offers ALTER selling_minor SET NOT NULL, ALTER currency SET NOT NULL');
        $this->addSql("ALTER TABLE shipping_quote_offers ADD CHECK (selling_minor BETWEEN 0 AND 100000000), ADD CHECK (currency IN ('EUR','GBP','USD','CHF')), ADD CHECK ((snapshot #>> '{price,totalMinor}')::bigint = selling_minor), ADD CHECK (snapshot #>> '{price,currency}' = currency)");
        $this->addSql('ALTER TABLE shipping_quote_offers ENABLE TRIGGER offer_immutable');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Preserve historical offer prices.');
    }
}
