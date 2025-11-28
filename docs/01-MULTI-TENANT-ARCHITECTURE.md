# Architecture Multi-Tenant pour CSWeb Pro

## Contexte

Extension de CSWeb 8 pour supporter :
- **Multi-tenancy** : Plusieurs structures/organisations avec isolation complète des données
- **Multi-SGBD** : Chaque structure choisit son SGBD (MySQL, PostgreSQL, SQL Server)
- **Breakout par DICTIONARY** : Configuration flexible de la base de données destinataire

## État Actuel (CSWeb 8 Modifié)

### Tables Existantes

```sql
-- Stocke les dictionnaires CSPro
CREATE TABLE `cspro_dictionaries` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `dictionary_name` varchar(191) NOT NULL,
  `dictionary_label` varchar(255) NOT NULL,
  `dictionary_full_content` longtext NOT NULL,
  PRIMARY KEY (`id`)
);

-- Stocke les connexions PostgreSQL par dictionnaire (VOTRE AJOUT)
CREATE TABLE `cspro_dictionaries_schema` (
  `dictionary_id` smallint unsigned NOT NULL,
  `host_name` varchar(191) NOT NULL,
  `schema_name` varchar(191) NOT NULL,
  `schema_user_name` varchar(255) NOT NULL,
  `schema_password` VARBINARY(255) NOT NULL,  -- Chiffré avec AES
  `additional_config` TEXT,
  `map_info` TEXT,
  PRIMARY KEY (`dictionary_id`),
  CONSTRAINT FOREIGN KEY (`dictionary_id`) REFERENCES `cspro_dictionaries`(`id`)
);
```

### Services Existants

- `PdoHelper` : Connexion MySQL
- `PdoPOSTGRESHelper` : Connexion PostgreSQL (VOTRE AJOUT)
- `DictionarySchemaHelper` : Gestion du schéma avec `getConnectionParameters()`

## Architecture Proposée - Multi-Tenant

### 1. Nouvelle Structure de Tables

```sql
-- ============================================
-- TABLE 1: Organisations/Structures (Tenants)
-- ============================================
CREATE TABLE IF NOT EXISTS `cspro_organizations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_code` VARCHAR(50) NOT NULL UNIQUE,  -- Ex: "ANSD", "INS_BENIN"
  `organization_name` VARCHAR(255) NOT NULL,        -- Ex: "ANSD Sénégal"
  `organization_type` ENUM('statistics_office', 'research', 'survey_firm', 'ngo', 'government', 'other') DEFAULT 'statistics_office',
  `country_code` CHAR(2),                           -- ISO 3166-1 alpha-2
  `contact_email` VARCHAR(255),
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modified_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_org_code` (`organization_code`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 2: Connexions de Base de Données par Organisation
-- ============================================
CREATE TABLE IF NOT EXISTS `cspro_organization_db_connections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL,
  `connection_name` VARCHAR(100) NOT NULL,          -- Ex: "POSTGRES_PROD", "MYSQL_DEV"
  `db_driver` ENUM('pdo_mysql', 'pdo_pgsql', 'sqlsrv') NOT NULL,
  `db_host` VARCHAR(255) NOT NULL,
  `db_port` INT,
  `db_name` VARCHAR(255) NOT NULL,
  `db_user` VARCHAR(255) NOT NULL,
  `db_password_encrypted` VARBINARY(512) NOT NULL,  -- Chiffré avec clé spécifique
  `db_charset` VARCHAR(20) DEFAULT 'utf8mb4',
  `connection_options` JSON,                        -- Options additionnelles (SSL, etc.)
  `is_default` BOOLEAN DEFAULT FALSE,               -- Connexion par défaut pour l'org
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modified_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_org_conn_org` FOREIGN KEY (`organization_id`)
    REFERENCES `cspro_organizations`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_org_conn_name` (`organization_id`, `connection_name`),
  INDEX `idx_org_conn_driver` (`db_driver`),
  INDEX `idx_org_conn_default` (`organization_id`, `is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 3: Modifier cspro_dictionaries pour ajouter organization_id
-- ============================================
ALTER TABLE `cspro_dictionaries`
  ADD COLUMN `organization_id` INT UNSIGNED AFTER `id`,
  ADD COLUMN `db_connection_id` INT UNSIGNED AFTER `organization_id`,
  ADD CONSTRAINT `fk_dict_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `cspro_organizations`(`id`),
  ADD CONSTRAINT `fk_dict_db_connection`
    FOREIGN KEY (`db_connection_id`) REFERENCES `cspro_organization_db_connections`(`id`),
  ADD INDEX `idx_dict_org` (`organization_id`);

-- ============================================
-- TABLE 4: Utilisateurs avec Organisation
-- ============================================
-- Modifier la table utilisateurs existante (à adapter selon votre table users)
ALTER TABLE `cspro_users`
  ADD COLUMN `organization_id` INT UNSIGNED AFTER `id`,
  ADD CONSTRAINT `fk_user_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `cspro_organizations`(`id`),
  ADD INDEX `idx_user_org` (`organization_id`);

-- ============================================
-- TABLE 5: Garder cspro_dictionaries_schema pour rétrocompatibilité
-- ============================================
-- On garde la table existante mais on ajoute une référence à la nouvelle architecture
ALTER TABLE `cspro_dictionaries_schema`
  ADD COLUMN `db_connection_id` INT UNSIGNED AFTER `dictionary_id`,
  ADD CONSTRAINT `fk_schema_db_connection`
    FOREIGN KEY (`db_connection_id`) REFERENCES `cspro_organization_db_connections`(`id`) ON DELETE SET NULL;
```

### 2. Service DatabaseConnectionManager

Créer un nouveau service central pour gérer toutes les connexions :

```php
// src/AppBundle/Service/DatabaseConnectionManager.php
<?php
namespace AppBundle\Service;

use Psr\Log\LoggerInterface;
use AppBundle\Service\PdoHelper;
use AppBundle\Service\PdoPOSTGRESHelper;

class DatabaseConnectionManager
{
    private array $connectionPool = [];
    private string $encryptionKey;

    public function __construct(
        private PdoHelper $mainPdo,
        private LoggerInterface $logger,
        string $encryptionKey
    ) {
        $this->encryptionKey = $encryptionKey;
    }

    /**
     * Obtenir une connexion pour un dictionnaire donné
     *
     * @param string $dictionaryName Nom du dictionnaire
     * @param int|null $organizationId ID de l'organisation (pour isolation multi-tenant)
     * @return \PDO|\Aura\Sql\ExtendedPdo
     */
    public function getConnectionForDictionary(
        string $dictionaryName,
        ?int $organizationId = null
    ) {
        // 1. Récupérer la config de connexion depuis la DB
        $connectionConfig = $this->getConnectionConfig($dictionaryName, $organizationId);

        if (!$connectionConfig) {
            throw new \Exception("No database connection configured for dictionary: $dictionaryName");
        }

        // 2. Vérifier le pool de connexions
        $poolKey = $this->getPoolKey($connectionConfig);

        if (isset($this->connectionPool[$poolKey])) {
            $this->logger->debug("Reusing connection from pool: $poolKey");
            return $this->connectionPool[$poolKey];
        }

        // 3. Créer nouvelle connexion
        $connection = $this->createConnection($connectionConfig);

        // 4. Ajouter au pool
        $this->connectionPool[$poolKey] = $connection;

        return $connection;
    }

    /**
     * Obtenir la connexion par défaut d'une organisation
     */
    public function getDefaultConnectionForOrganization(int $organizationId)
    {
        $stm = "SELECT * FROM `cspro_organization_db_connections`
                WHERE `organization_id` = :orgId AND `is_default` = TRUE AND `is_active` = TRUE
                LIMIT 1";

        $result = $this->mainPdo->fetchOne($stm, ['orgId' => $organizationId]);

        if (!$result) {
            throw new \Exception("No default database connection for organization ID: $organizationId");
        }

        return $this->createConnectionFromConfig($result);
    }

    /**
     * Récupérer la configuration de connexion pour un dictionnaire
     */
    private function getConnectionConfig(string $dictionaryName, ?int $organizationId): ?array
    {
        $params = ['dictName' => $dictionaryName];

        // Requête avec ou sans isolation par organisation
        if ($organizationId !== null) {
            $stm = "SELECT conn.*
                    FROM `cspro_dictionaries` dict
                    JOIN `cspro_organization_db_connections` conn ON dict.db_connection_id = conn.id
                    WHERE dict.dictionary_name = :dictName
                      AND dict.organization_id = :orgId
                      AND conn.is_active = TRUE";
            $params['orgId'] = $organizationId;
        } else {
            // Rétrocompatibilité avec ancien système (cspro_dictionaries_schema)
            $stm = "SELECT
                        schema.host_name as db_host,
                        schema.schema_name as db_name,
                        schema.schema_user_name as db_user,
                        AES_DECRYPT(schema.schema_password, 'cspro') as db_password,
                        'pdo_pgsql' as db_driver
                    FROM `cspro_dictionaries` dict
                    JOIN `cspro_dictionaries_schema` schema ON dict.id = schema.dictionary_id
                    WHERE dict.dictionary_name = :dictName";
        }

        $result = $this->mainPdo->fetchOne($stm, $params);

        return $result ?: null;
    }

    /**
     * Créer une connexion PDO/ExtendedPdo selon le driver
     */
    private function createConnectionFromConfig(array $config)
    {
        // Déchiffrer le mot de passe
        $password = $this->decryptPassword($config['db_password_encrypted']);

        switch ($config['db_driver']) {
            case 'pdo_mysql':
                return new PdoHelper(
                    $config['db_host'],
                    $config['db_name'],
                    $config['db_user'],
                    $password
                );

            case 'pdo_pgsql':
                return new PdoPOSTGRESHelper(
                    $config['db_host'],
                    $config['db_name'],
                    $config['db_user'],
                    $password
                );

            case 'sqlsrv':
                // TODO: Créer PdoSQLServerHelper
                return $this->createSQLServerConnection($config, $password);

            default:
                throw new \Exception("Unsupported database driver: {$config['db_driver']}");
        }
    }

    /**
     * Créer connexion SQL Server (à implémenter)
     */
    private function createSQLServerConnection(array $config, string $password)
    {
        // TODO: Implémenter PdoSQLServerHelper
        throw new \Exception("SQL Server support not yet implemented");
    }

    /**
     * Déchiffrer le mot de passe
     */
    private function decryptPassword(string $encryptedPassword): string
    {
        // Utiliser la même méthode de chiffrement que CSWeb actuel
        // Actuellement: AES_DECRYPT avec clé 'cspro'
        // Pour production: utiliser une clé plus robuste

        $decrypted = openssl_decrypt(
            $encryptedPassword,
            'AES-256-CBC',
            $this->encryptionKey,
            0,
            substr(hash('sha256', $this->encryptionKey), 0, 16)
        );

        if ($decrypted === false) {
            throw new \Exception("Failed to decrypt database password");
        }

        return $decrypted;
    }

    /**
     * Générer clé unique pour le pool de connexions
     */
    private function getPoolKey(array $config): string
    {
        return md5(json_encode([
            'driver' => $config['db_driver'],
            'host' => $config['db_host'],
            'name' => $config['db_name'],
            'user' => $config['db_user']
        ]));
    }

    /**
     * Fermer toutes les connexions du pool
     */
    public function closeAllConnections(): void
    {
        $this->connectionPool = [];
        $this->logger->info("Closed all database connections in pool");
    }
}
```

### 3. Enregistrer le Service dans Symfony

Modifier `app/config/services.yml` :

```yaml
services:
    # ... services existants ...

    # Database Connection Manager
    AppBundle\Service\DatabaseConnectionManager:
        public: true
        arguments:
            $mainPdo: '@AppBundle\Service\PdoHelper'
            $logger: '@logger'
            $encryptionKey: '%database_encryption_key%'
```

Ajouter dans `app/config/parameters.yml.dist` :

```yaml
parameters:
    # ... paramètres existants ...
    database_encryption_key: 'change-me-to-secure-key'
```

### 4. Modifier DictionaryHelper pour utiliser le ConnectionManager

```php
// Dans DictionaryHelper.php, modifier le constructeur et les méthodes

use AppBundle\Service\DatabaseConnectionManager;

class DictionaryHelper {

    public function __construct(
        private PdoHelper $pdo,  // Connexion principale (MySQL/CSWeb)
        private LoggerInterface $logger,
        private string $serverDeviceId,
        private ?DatabaseConnectionManager $connectionManager = null  // Nouveau
    ) {
        // ...
    }

    // Nouvelle méthode pour obtenir la connexion appropriée
    private function getConnection(string $dictName, ?int $organizationId = null)
    {
        if ($this->connectionManager) {
            return $this->connectionManager->getConnectionForDictionary($dictName, $organizationId);
        }

        // Fallback vers connexion principale
        return $this->pdo;
    }

    // Modifier toutes les méthodes qui accèdent à $this->pdo
    // pour utiliser $this->getConnection() à la place
}
```

## 5. Interface Admin pour la Configuration

### Écrans à créer

1. **Gestion des Organisations**
   - Liste des organisations
   - Création/édition d'organisation
   - Activation/désactivation

2. **Gestion des Connexions DB**
   - Par organisation
   - Test de connexion
   - Définir connexion par défaut

3. **Configuration des Dictionnaires**
   - Assigner un dictionnaire à une organisation
   - Choisir la connexion DB destinataire
   - Visualiser les données breakout

## 6. Sécurité et Isolation

### Row-Level Security (RLS)

Pour PostgreSQL, utiliser RLS :

```sql
-- Activer RLS sur les tables de données
ALTER TABLE dictionary_data_table ENABLE ROW LEVEL SECURITY;

-- Politique : Un utilisateur ne voit que les données de son organisation
CREATE POLICY org_isolation_policy ON dictionary_data_table
  USING (organization_id = current_setting('app.current_organization_id')::int);
```

### Middleware de Sécurité

Créer un middleware Symfony pour :
- Identifier l'organisation de l'utilisateur courant
- Filtrer automatiquement toutes les requêtes par `organization_id`
- Logger les accès cross-organization (audit)

## 7. Migration depuis l'Architecture Actuelle

### Script de Migration

```php
// migrate_to_multitenant.php

// 1. Créer une organisation par défaut
INSERT INTO cspro_organizations (organization_code, organization_name, is_active)
VALUES ('DEFAULT', 'Default Organization', TRUE);

// 2. Migrer les connexions existantes
INSERT INTO cspro_organization_db_connections
  (organization_id, connection_name, db_driver, db_host, db_name, db_user, db_password_encrypted, is_default)
SELECT
  (SELECT id FROM cspro_organizations WHERE organization_code = 'DEFAULT'),
  CONCAT('POSTGRES_', schema.schema_name),
  'pdo_pgsql',
  schema.host_name,
  schema.schema_name,
  schema.schema_user_name,
  schema.schema_password,
  TRUE
FROM cspro_dictionaries_schema schema;

// 3. Mettre à jour les dictionnaires
UPDATE cspro_dictionaries dict
JOIN cspro_dictionaries_schema schema ON dict.id = schema.dictionary_id
JOIN cspro_organization_db_connections conn ON conn.db_name = schema.schema_name
SET
  dict.organization_id = (SELECT id FROM cspro_organizations WHERE organization_code = 'DEFAULT'),
  dict.db_connection_id = conn.id;
```

## 8. Avantages de cette Architecture

✅ **Isolation complète** : Chaque organisation ne voit que ses données
✅ **Flexibilité SGBD** : MySQL, PostgreSQL, SQL Server au choix
✅ **Scalabilité** : Connection pooling pour performances
✅ **Sécurité** : Chiffrement des credentials, RLS PostgreSQL
✅ **Rétrocompatibilité** : Support de l'ancienne table `cspro_dictionaries_schema`
✅ **SaaS-ready** : Prêt pour du multi-tenant en production

## 9. Prochaines Étapes

1. ✅ Créer les tables SQL (migration)
2. ⏳ Implémenter `DatabaseConnectionManager`
3. ⏳ Créer `PdoSQLServerHelper` pour SQL Server
4. ⏳ Modifier `DictionaryHelper` pour utiliser le ConnectionManager
5. ⏳ Créer l'interface admin (CRUD organisations + connexions)
6. ⏳ Implémenter le middleware de sécurité
7. ⏳ Tests unitaires et d'intégration
8. ⏳ Documentation utilisateur

---

**Note** : Cette architecture est basée sur votre implémentation actuelle et les principes de [.claude/instructions.md](../.claude/instructions.md).
