<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity, relational RBAC, single-use tokens and append-only audit foundation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE users (id VARCHAR(36) PRIMARY KEY, email VARCHAR(180) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, password_hash VARCHAR(255) NOT NULL, kind VARCHAR(16) NOT NULL CHECK (kind IN ('staff','customer')), status VARCHAR(32) NOT NULL CHECK (status IN ('invited','pending_verification','active','suspended')), session_version INT NOT NULL DEFAULT 1, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL)");
        $this->addSql('CREATE INDEX users_kind_status_idx ON users(kind,status)');
        $this->addSql('CREATE TABLE roles (code VARCHAR(64) PRIMARY KEY, description TEXT NOT NULL)');
        $this->addSql('CREATE TABLE permissions (code VARCHAR(100) PRIMARY KEY)');
        $this->addSql('CREATE TABLE user_roles (user_id VARCHAR(36) NOT NULL REFERENCES users(id) ON DELETE CASCADE, role_code VARCHAR(64) NOT NULL REFERENCES roles(code), PRIMARY KEY(user_id,role_code))');
        $this->addSql('CREATE INDEX user_roles_role_idx ON user_roles(role_code)');
        $this->addSql('CREATE TABLE role_permissions (role_code VARCHAR(64) NOT NULL REFERENCES roles(code), permission_code VARCHAR(100) NOT NULL REFERENCES permissions(code), PRIMARY KEY(role_code,permission_code))');
        $this->addSql('CREATE INDEX role_permissions_permission_idx ON role_permissions(permission_code)');
        foreach (['staff_invitations', 'email_verification_tokens', 'password_reset_tokens'] as $table) {
            $this->addSql("CREATE TABLE $table (token_hash VARCHAR(64) PRIMARY KEY, user_id VARCHAR(36) NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL)");
            $this->addSql("CREATE INDEX {$table}_expiry_idx ON $table(expires_at)");
        }
        $this->addSql('CREATE TABLE audit_events (id VARCHAR(36) PRIMARY KEY, actor_id VARCHAR(36) REFERENCES users(id) ON DELETE SET NULL, action VARCHAR(100) NOT NULL, target_id VARCHAR(36), correlation_id VARCHAR(36) NOT NULL, context JSONB NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL)');
        $this->addSql('CREATE INDEX audit_events_time_idx ON audit_events(created_at)');
        $this->addSql('CREATE INDEX audit_events_action_idx ON audit_events(action,created_at)');
        $this->addSql('CREATE INDEX audit_events_actor_idx ON audit_events(actor_id,created_at)');
        $this->addSql('CREATE INDEX audit_events_target_idx ON audit_events(target_id,created_at)');
        $this->addSql("INSERT INTO permissions(code) VALUES ('audit.read')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('carriers.dashboard.view')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('customers.read_limited')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('finance.dashboard.view')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('marketing.dashboard.view')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('operations.dashboard.view')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('permissions.manage')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('permissions.read')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('platform.dashboard.view')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('qa.dashboard.view')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('roles.assign')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('roles.read')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('staff.invite')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('staff.read')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('staff.suspend')");
        $this->addSql("INSERT INTO permissions(code) VALUES ('support.dashboard.view')");
        $this->addSql("INSERT INTO roles(code,description) VALUES ('SUPER_ADMIN','Full security and staff administration')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','platform.dashboard.view')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','staff.read')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','staff.invite')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','staff.suspend')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','roles.read')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','roles.assign')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','permissions.read')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','permissions.manage')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','audit.read')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','qa.dashboard.view')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','finance.dashboard.view')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','marketing.dashboard.view')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','operations.dashboard.view')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','support.dashboard.view')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','carriers.dashboard.view')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('SUPER_ADMIN','customers.read_limited')");
        $this->addSql("INSERT INTO roles(code,description) VALUES ('PLATFORM_ADMIN','Daily account operations without security role management')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('PLATFORM_ADMIN','platform.dashboard.view')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('PLATFORM_ADMIN','staff.read')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('PLATFORM_ADMIN','staff.suspend')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('PLATFORM_ADMIN','roles.read')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('PLATFORM_ADMIN','permissions.read')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('PLATFORM_ADMIN','audit.read')");
        $this->addSql("INSERT INTO roles(code,description) VALUES ('OPERATIONS_MANAGER','Shipment operations foundation')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('OPERATIONS_MANAGER','operations.dashboard.view')");
        $this->addSql("INSERT INTO roles(code,description) VALUES ('QA_ENGINEER','Release and environment visibility')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('QA_ENGINEER','qa.dashboard.view')");
        $this->addSql("INSERT INTO roles(code,description) VALUES ('FINANCE_MANAGER','Finance workspace')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('FINANCE_MANAGER','finance.dashboard.view')");
        $this->addSql("INSERT INTO roles(code,description) VALUES ('MARKETING_MANAGER','Marketing workspace and aggregate growth')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('MARKETING_MANAGER','marketing.dashboard.view')");
        $this->addSql("INSERT INTO roles(code,description) VALUES ('CUSTOMER_SUPPORT','Support workspace and limited customer lookup')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('CUSTOMER_SUPPORT','support.dashboard.view')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('CUSTOMER_SUPPORT','customers.read_limited')");
        $this->addSql("INSERT INTO roles(code,description) VALUES ('CARRIER_MANAGER','Carrier workspace')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('CARRIER_MANAGER','carriers.dashboard.view')");
        $this->addSql("INSERT INTO roles(code,description) VALUES ('AUDITOR','Read-only sanitized audit information')");
        $this->addSql("INSERT INTO role_permissions(role_code,permission_code) VALUES ('AUDITOR','audit.read')");
        $this->addSql("INSERT INTO roles(code,description) VALUES ('CUSTOMER','Own customer profile only')");
    }

    public function down(Schema $schema): void
    {
        foreach (['audit_events', 'password_reset_tokens', 'email_verification_tokens', 'staff_invitations', 'role_permissions', 'user_roles', 'permissions', 'roles', 'users'] as $table) {
            $this->addSql('DROP TABLE '.$table);
        }
    }
}
