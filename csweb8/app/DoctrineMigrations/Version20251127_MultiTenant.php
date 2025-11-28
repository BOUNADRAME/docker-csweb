<?php

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour l'architecture Multi-Tenant CSWeb
 *
 * Cette migration ajoute:
 * - Table cspro_organizations pour gérer les structures/organisations
 * - Table cspro_organization_db_connections pour les connexions multi-SGBD
 * - Modification de cspro_dictionaries pour ajouter organization_id et db_connection_id
 */
final class Version20251127_MultiTenant extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add multi-tenant support with flexible database connections per organization';
    }

    public function up(Schema $schema): void
    {
        // 1. Créer la table des organisations
        $this->addSql("
            CREATE TABLE IF NOT EXISTS `cspro_organizations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `organization_code` VARCHAR(50) NOT NULL UNIQUE,
                `organization_name` VARCHAR(255) NOT NULL,
                `organization_type` VARCHAR(50) DEFAULT 'statistics_office',
                `country_code` CHAR(2) NULL,
                `contact_email` VARCHAR(255) NULL,
                `is_active` BOOLEAN DEFAULT TRUE,
                `created_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `modified_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_org_code` (`organization_code`),
                INDEX `idx_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. Créer la table des connexions de base de données
        $this->addSql("
            CREATE TABLE IF NOT EXISTS `cspro_organization_db_connections` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `organization_id` INT UNSIGNED NOT NULL,
                `connection_name` VARCHAR(100) NOT NULL,
                `db_driver` ENUM('pdo_mysql', 'pdo_pgsql', 'sqlsrv') NOT NULL,
                `db_host` VARCHAR(255) NOT NULL,
                `db_port` INT NULL,
                `db_name` VARCHAR(255) NOT NULL,
                `db_user` VARCHAR(255) NOT NULL,
                `db_password_encrypted` VARBINARY(512) NOT NULL,
                `db_charset` VARCHAR(20) DEFAULT 'utf8mb4',
                `connection_options` JSON NULL,
                `is_default` BOOLEAN DEFAULT FALSE,
                `is_active` BOOLEAN DEFAULT TRUE,
                `created_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `modified_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_org_conn_org` FOREIGN KEY (`organization_id`)
                    REFERENCES `cspro_organizations`(`id`) ON DELETE CASCADE,
                UNIQUE KEY `uk_org_conn_name` (`organization_id`, `connection_name`),
                INDEX `idx_org_conn_driver` (`db_driver`),
                INDEX `idx_org_conn_default` (`organization_id`, `is_default`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. Ajouter les colonnes à cspro_dictionaries si elles n'existent pas
        $this->addSql("
            ALTER TABLE `cspro_dictionaries`
            ADD COLUMN IF NOT EXISTS `organization_id` INT UNSIGNED NULL AFTER `id`,
            ADD COLUMN IF NOT EXISTS `db_connection_id` INT UNSIGNED NULL AFTER `organization_id`
        ");

        // 4. Ajouter les contraintes de clés étrangères
        $this->addSql("
            ALTER TABLE `cspro_dictionaries`
            ADD CONSTRAINT `fk_dict_organization`
                FOREIGN KEY (`organization_id`) REFERENCES `cspro_organizations`(`id`)
                ON DELETE SET NULL,
            ADD CONSTRAINT `fk_dict_db_connection`
                FOREIGN KEY (`db_connection_id`) REFERENCES `cspro_organization_db_connections`(`id`)
                ON DELETE SET NULL
        ");

        // 5. Ajouter index pour performance
        $this->addSql("
            ALTER TABLE `cspro_dictionaries`
            ADD INDEX `idx_dict_org` (`organization_id`)
        ");

        // 6. Modifier cspro_dictionaries_schema pour ajouter référence (rétrocompatibilité)
        $this->addSql("
            ALTER TABLE `cspro_dictionaries_schema`
            ADD COLUMN IF NOT EXISTS `db_connection_id` INT UNSIGNED NULL AFTER `dictionary_id`,
            ADD CONSTRAINT `fk_schema_db_connection`
                FOREIGN KEY (`db_connection_id`)
                REFERENCES `cspro_organization_db_connections`(`id`)
                ON DELETE SET NULL
        ");

        // 7. Créer une organisation par défaut pour la migration
        $this->addSql("
            INSERT INTO `cspro_organizations`
                (`organization_code`, `organization_name`, `organization_type`, `is_active`)
            VALUES
                ('DEFAULT', 'Default Organization', 'statistics_office', TRUE)
            ON DUPLICATE KEY UPDATE `organization_name` = 'Default Organization'
        ");
    }

    public function down(Schema $schema): void
    {
        // Supprimer dans l'ordre inverse

        // 1. Supprimer les contraintes de cspro_dictionaries_schema
        $this->addSql("
            ALTER TABLE `cspro_dictionaries_schema`
            DROP FOREIGN KEY IF EXISTS `fk_schema_db_connection`,
            DROP COLUMN IF EXISTS `db_connection_id`
        ");

        // 2. Supprimer les index et contraintes de cspro_dictionaries
        $this->addSql("
            ALTER TABLE `cspro_dictionaries`
            DROP FOREIGN KEY IF EXISTS `fk_dict_organization`,
            DROP FOREIGN KEY IF EXISTS `fk_dict_db_connection`,
            DROP INDEX IF EXISTS `idx_dict_org`,
            DROP COLUMN IF EXISTS `organization_id`,
            DROP COLUMN IF EXISTS `db_connection_id`
        ");

        // 3. Supprimer les tables
        $this->addSql("DROP TABLE IF EXISTS `cspro_organization_db_connections`");
        $this->addSql("DROP TABLE IF EXISTS `cspro_organizations`");
    }
}
