<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Versioned development carrier catalog, immutable quotations and least-privilege administration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE carriers (code VARCHAR(40) PRIMARY KEY, name VARCHAR(100) NOT NULL, enabled BOOLEAN NOT NULL DEFAULT false, mode VARCHAR(32) NOT NULL CHECK (mode IN (\'unconfigured\',\'development_tariff\')), last_checked_at TIMESTAMPTZ)');
        $this->addSql('CREATE TABLE carrier_services (code VARCHAR(60) PRIMARY KEY, carrier_code VARCHAR(40) NOT NULL REFERENCES carriers(code), enabled BOOLEAN NOT NULL DEFAULT false, rules JSONB NOT NULL)');
        $this->addSql('CREATE TABLE carrier_tariffs (id VARCHAR(36) PRIMARY KEY, service_code VARCHAR(60) NOT NULL REFERENCES carrier_services(code), version VARCHAR(80) NOT NULL, active BOOLEAN NOT NULL, effective_from TIMESTAMPTZ NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, configuration JSONB NOT NULL, UNIQUE(service_code,version))');
        $this->addSql('CREATE UNIQUE INDEX tariff_active_idx ON carrier_tariffs(service_code) WHERE active');
        $this->addSql('CREATE TABLE pricing_rules (id VARCHAR(36) PRIMARY KEY, service_code VARCHAR(60) REFERENCES carrier_services(code), version VARCHAR(80) NOT NULL UNIQUE, active BOOLEAN NOT NULL, configuration JSONB NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->addSql('CREATE UNIQUE INDEX pricing_active_service_idx ON pricing_rules(COALESCE(service_code,\'\')) WHERE active');
        $this->addSql('CREATE TABLE shipping_quotes (id VARCHAR(36) PRIMARY KEY, owner_id VARCHAR(36) REFERENCES users(id) ON DELETE CASCADE, guest_token_hash VARCHAR(64), scope_hash VARCHAR(64) NOT NULL, idempotency_key VARCHAR(64) NOT NULL, request_hash VARCHAR(64) NOT NULL, request JSONB NOT NULL, outcomes JSONB NOT NULL, created_at TIMESTAMPTZ NOT NULL, expires_at TIMESTAMPTZ NOT NULL, purge_at TIMESTAMPTZ NOT NULL, CHECK (expires_at > created_at), CHECK ((owner_id IS NULL) = (guest_token_hash IS NOT NULL)), UNIQUE(scope_hash,idempotency_key))');
        $this->addSql('CREATE INDEX quotes_retention_idx ON shipping_quotes(purge_at)');
        $this->addSql('CREATE INDEX quotes_owner_idx ON shipping_quotes(owner_id,created_at)');
        $this->addSql('CREATE TABLE shipping_quote_offers (id VARCHAR(36) PRIMARY KEY, quote_id VARCHAR(36) NOT NULL REFERENCES shipping_quotes(id) ON DELETE CASCADE, snapshot JSONB NOT NULL)');
        $this->addSql('CREATE INDEX quote_offers_quote_idx ON shipping_quote_offers(quote_id)');
        $this->addSql('CREATE FUNCTION protect_quote_snapshot() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION \'Quote snapshots are immutable\'; END $$');
        $this->addSql('CREATE TRIGGER quote_immutable BEFORE UPDATE ON shipping_quotes FOR EACH ROW EXECUTE FUNCTION protect_quote_snapshot()');
        $this->addSql('CREATE TRIGGER offer_immutable BEFORE UPDATE ON shipping_quote_offers FOR EACH ROW EXECUTE FUNCTION protect_quote_snapshot()');
        $this->addSql('CREATE FUNCTION protect_tariff_version() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF (to_jsonb(NEW)-\'active\') IS DISTINCT FROM (to_jsonb(OLD)-\'active\') THEN RAISE EXCEPTION \'Historical versions are immutable\'; END IF; RETURN NEW; END $$');
        $this->addSql('CREATE TRIGGER tariff_immutable BEFORE UPDATE ON carrier_tariffs FOR EACH ROW EXECUTE FUNCTION protect_tariff_version()');
        $this->addSql('CREATE TRIGGER pricing_immutable BEFORE UPDATE ON pricing_rules FOR EACH ROW EXECUTE FUNCTION protect_tariff_version()');
        $this->addSql('INSERT INTO carriers(code,name,mode) VALUES (\'DHL\',\'DHL\',\'unconfigured\')');
        $this->addSql('INSERT INTO carriers(code,name,mode) VALUES (\'DPD\',\'DPD\',\'unconfigured\')');
        $this->addSql('INSERT INTO carriers(code,name,mode) VALUES (\'GLS\',\'GLS\',\'unconfigured\')');
        $this->addSql('INSERT INTO carriers(code,name,mode) VALUES (\'UPS\',\'UPS\',\'unconfigured\')');
        $this->addSql('INSERT INTO carriers(code,name,enabled,mode) VALUES (\'DEV_POST\',\'ShipWise Development Tariff\',true,\'development_tariff\')');
        $this->addSql('INSERT INTO carrier_services(code,carrier_code,enabled,rules) VALUES (\'DEV_STANDARD\',\'DEV_POST\',true,\'{"routes": ["DE:DE", "DE:FR"], "maxWeightGrams": 30000, "maxDimensionsMm": [1200, 600, 600], "handover": ["drop_off"], "speed": "standard"}\')');
        $this->addSql('INSERT INTO carrier_tariffs(id,service_code,version,active,effective_from,configuration) VALUES (\'00000000-0000-4000-8000-000000000001\',\'DEV_STANDARD\',\'dev-tariff-1\',true,\'2026-01-01T00:00:00Z\',\'{"baseMinor": 400, "perKgMinor": 50, "surchargeMinor": 0, "currency": "EUR"}\')');
        $this->addSql('INSERT INTO carrier_services(code,carrier_code,enabled,rules) VALUES (\'DEV_EXPRESS\',\'DEV_POST\',true,\'{"routes": ["DE:DE", "DE:FR"], "maxWeightGrams": 20000, "maxDimensionsMm": [1200, 600, 600], "handover": ["drop_off", "pickup"], "speed": "express"}\')');
        $this->addSql('INSERT INTO carrier_tariffs(id,service_code,version,active,effective_from,configuration) VALUES (\'00000000-0000-4000-8000-000000000002\',\'DEV_EXPRESS\',\'dev-tariff-1\',true,\'2026-01-01T00:00:00Z\',\'{"baseMinor": 800, "perKgMinor": 80, "surchargeMinor": 0, "currency": "EUR"}\')');
        $this->addSql('INSERT INTO pricing_rules(id,version,active,configuration) VALUES (\'00000000-0000-4000-8000-000000000010\',\'dev-pricing-1\',true,\'{"currency": "EUR", "version": "dev-pricing-1", "fixedMarginMinor": 100, "marginBasisPoints": 1000, "minimumMarginMinor": 150, "feeMinor": 25, "taxRoutes": {"DE:DE": {"basisPoints": 1900, "version": "synthetic-tax-1"}, "DE:FR": {"basisPoints": 1900, "version": "synthetic-tax-1"}}}\')');
        $this->addSql('INSERT INTO permissions(code) VALUES (\'carriers.catalog.read\')');
        $this->addSql('INSERT INTO role_permissions(role_code,permission_code) VALUES (\'SUPER_ADMIN\',\'carriers.catalog.read\')');
        $this->addSql('INSERT INTO role_permissions(role_code,permission_code) VALUES (\'CARRIER_MANAGER\',\'carriers.catalog.read\')');
        $this->addSql('INSERT INTO permissions(code) VALUES (\'carriers.configure\')');
        $this->addSql('INSERT INTO role_permissions(role_code,permission_code) VALUES (\'SUPER_ADMIN\',\'carriers.configure\')');
        $this->addSql('INSERT INTO role_permissions(role_code,permission_code) VALUES (\'CARRIER_MANAGER\',\'carriers.configure\')');
        $this->addSql('INSERT INTO permissions(code) VALUES (\'tariffs.manage\')');
        $this->addSql('INSERT INTO role_permissions(role_code,permission_code) VALUES (\'SUPER_ADMIN\',\'tariffs.manage\')');
        $this->addSql('INSERT INTO role_permissions(role_code,permission_code) VALUES (\'CARRIER_MANAGER\',\'tariffs.manage\')');
        $this->addSql('INSERT INTO permissions(code) VALUES (\'pricing.manage\')');
        $this->addSql('INSERT INTO role_permissions(role_code,permission_code) VALUES (\'SUPER_ADMIN\',\'pricing.manage\')');
        $this->addSql('INSERT INTO permissions(code) VALUES (\'quotes.diagnostics\')');
        $this->addSql('INSERT INTO role_permissions(role_code,permission_code) VALUES (\'SUPER_ADMIN\',\'quotes.diagnostics\')');
        $this->addSql('INSERT INTO role_permissions(role_code,permission_code) VALUES (\'CARRIER_MANAGER\',\'quotes.diagnostics\')');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Preserve historical quote and tariff data.');
    }
}
