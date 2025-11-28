<?php

namespace AppBundle\Service;

use Psr\Log\LoggerInterface;
use AppBundle\Service\PdoHelper;
use AppBundle\Service\PdoPOSTGRESHelper;
use AppBundle\Entity\DatabaseConnection;
use AppBundle\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestionnaire centralisé des connexions de bases de données multi-SGBD
 *
 * Ce service gère:
 * - Connection pooling pour optimiser les performances
 * - Support MySQL, PostgreSQL, SQL Server
 * - Chiffrement/déchiffrement des credentials
 * - Isolation par organisation (multi-tenant)
 */
class DatabaseConnectionManager
{
    private array $connectionPool = [];
    private string $encryptionKey;
    private string $encryptionMethod = 'AES-256-CBC';

    public function __construct(
        private PdoHelper $mainPdo,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        string $encryptionKey
    ) {
        $this->encryptionKey = $encryptionKey;
    }

    /**
     * Obtenir une connexion pour un dictionnaire donné
     *
     * @param string $dictionaryName Nom du dictionnaire
     * @param int|null $organizationId ID de l'organisation (isolation multi-tenant)
     * @return \Aura\Sql\ExtendedPdo
     * @throws \Exception
     */
    public function getConnectionForDictionary(
        string $dictionaryName,
        ?int $organizationId = null
    ) {
        $this->logger->debug("Getting connection for dictionary: $dictionaryName, org: $organizationId");

        // 1. Récupérer la configuration depuis la DB
        $connectionConfig = $this->getConnectionConfigForDictionary($dictionaryName, $organizationId);

        if (!$connectionConfig) {
            throw new \Exception("No database connection configured for dictionary: $dictionaryName");
        }

        // 2. Vérifier le pool
        $poolKey = $this->getPoolKey($connectionConfig);

        if (isset($this->connectionPool[$poolKey])) {
            $this->logger->debug("Reusing pooled connection: $poolKey");
            return $this->connectionPool[$poolKey];
        }

        // 3. Créer nouvelle connexion
        $connection = $this->createConnection($connectionConfig);

        // 4. Ajouter au pool
        $this->connectionPool[$poolKey] = $connection;

        $this->logger->info("Created new connection: $poolKey");

        return $connection;
    }

    /**
     * Obtenir la connexion par défaut d'une organisation
     *
     * @param int $organizationId
     * @return \Aura\Sql\ExtendedPdo
     * @throws \Exception
     */
    public function getDefaultConnectionForOrganization(int $organizationId)
    {
        $connection = $this->entityManager
            ->getRepository(DatabaseConnection::class)
            ->findOneBy([
                'organization' => $organizationId,
                'isDefault' => true,
                'isActive' => true
            ]);

        if (!$connection) {
            throw new \Exception("No default database connection for organization ID: $organizationId");
        }

        return $this->createConnectionFromEntity($connection);
    }

    /**
     * Tester une connexion
     *
     * @param DatabaseConnection $connection
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(DatabaseConnection $connection): array
    {
        try {
            $pdo = $this->createConnectionFromEntity($connection);

            // Test simple
            $result = $pdo->fetchOne('SELECT 1 as test');

            return [
                'success' => true,
                'message' => 'Connection successful',
                'details' => [
                    'driver' => $connection->getDbDriver(),
                    'host' => $connection->getDbHost(),
                    'database' => $connection->getDbName()
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('Connection test failed', [
                'connection_id' => $connection->getId(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Chiffrer un mot de passe
     *
     * @param string $password
     * @return string
     */
    public function encryptPassword(string $password): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->encryptionMethod));
        $encrypted = openssl_encrypt($password, $this->encryptionMethod, $this->encryptionKey, 0, $iv);

        // Stocker IV avec le mot de passe chiffré
        return base64_encode($iv . $encrypted);
    }

    /**
     * Déchiffrer un mot de passe
     *
     * @param string $encryptedPassword
     * @return string
     * @throws \Exception
     */
    public function decryptPassword($encryptedPassword): string
    {
        // Si c'est déjà déchiffré (chaîne normale), retourner tel quel
        if (!is_string($encryptedPassword) && !is_resource($encryptedPassword)) {
            return $encryptedPassword;
        }

        // Gérer le cas VARBINARY (resource stream de MySQL)
        if (is_resource($encryptedPassword)) {
            $encryptedPassword = stream_get_contents($encryptedPassword);
        }

        try {
            $data = base64_decode($encryptedPassword);
            $ivLength = openssl_cipher_iv_length($this->encryptionMethod);
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);

            $decrypted = openssl_decrypt($encrypted, $this->encryptionMethod, $this->encryptionKey, 0, $iv);

            if ($decrypted === false) {
                throw new \Exception("Failed to decrypt password");
            }

            return $decrypted;
        } catch (\Exception $e) {
            $this->logger->error("Password decryption failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer la configuration pour un dictionnaire
     *
     * @param string $dictionaryName
     * @param int|null $organizationId
     * @return array|null
     */
    private function getConnectionConfigForDictionary(string $dictionaryName, ?int $organizationId): ?array
    {
        // Nouvelle méthode avec multi-tenant
        if ($organizationId !== null) {
            $stm = "SELECT
                        conn.id,
                        conn.connection_name,
                        conn.db_driver,
                        conn.db_host,
                        conn.db_port,
                        conn.db_name,
                        conn.db_user,
                        conn.db_password_encrypted,
                        conn.db_charset,
                        conn.connection_options
                    FROM `cspro_dictionaries` dict
                    JOIN `cspro_organization_db_connections` conn ON dict.db_connection_id = conn.id
                    WHERE dict.dictionary_name = :dictName
                      AND dict.organization_id = :orgId
                      AND conn.is_active = TRUE";

            $result = $this->mainPdo->fetchOne($stm, [
                'dictName' => $dictionaryName,
                'orgId' => $organizationId
            ]);

            if ($result) {
                return $result;
            }
        }

        // Fallback: Ancien système (rétrocompatibilité)
        $stm = "SELECT
                    schema.host_name as db_host,
                    schema.schema_name as db_name,
                    schema.schema_user_name as db_user,
                    schema.schema_password as db_password_encrypted,
                    'pdo_pgsql' as db_driver,
                    NULL as db_port,
                    'utf8' as db_charset,
                    NULL as connection_options
                FROM `cspro_dictionaries` dict
                JOIN `cspro_dictionaries_schema` schema ON dict.id = schema.dictionary_id
                WHERE dict.dictionary_name = :dictName";

        $result = $this->mainPdo->fetchOne($stm, ['dictName' => $dictionaryName]);

        return $result ?: null;
    }

    /**
     * Créer une connexion depuis une entité DatabaseConnection
     *
     * @param DatabaseConnection $connectionEntity
     * @return \Aura\Sql\ExtendedPdo
     */
    private function createConnectionFromEntity(DatabaseConnection $connectionEntity)
    {
        $config = [
            'db_driver' => $connectionEntity->getDbDriver(),
            'db_host' => $connectionEntity->getDbHost(),
            'db_port' => $connectionEntity->getDbPort(),
            'db_name' => $connectionEntity->getDbName(),
            'db_user' => $connectionEntity->getDbUser(),
            'db_password_encrypted' => $connectionEntity->getDbPasswordEncrypted(),
            'db_charset' => $connectionEntity->getDbCharset(),
            'connection_options' => $connectionEntity->getConnectionOptions()
        ];

        return $this->createConnection($config);
    }

    /**
     * Créer une connexion PDO selon le driver
     *
     * @param array $config
     * @return \Aura\Sql\ExtendedPdo
     * @throws \Exception
     */
    private function createConnection(array $config)
    {
        // Déchiffrer le mot de passe
        $password = $this->decryptPassword($config['db_password_encrypted']);

        $driver = $config['db_driver'];
        $host = $config['db_host'];
        $name = $config['db_name'];
        $user = $config['db_user'];
        $port = $config['db_port'] ?? null;

        switch ($driver) {
            case DatabaseConnection::DRIVER_MYSQL:
                $this->logger->debug("Creating MySQL connection to $host/$name");
                return new PdoHelper($host, $name, $user, $password);

            case DatabaseConnection::DRIVER_POSTGRESQL:
                $this->logger->debug("Creating PostgreSQL connection to $host/$name");
                return new PdoPOSTGRESHelper($host, $name, $user, $password);

            case DatabaseConnection::DRIVER_SQLSERVER:
                $this->logger->debug("Creating SQL Server connection to $host/$name");
                return $this->createSQLServerConnection($host, $name, $user, $password, $port);

            default:
                throw new \Exception("Unsupported database driver: $driver");
        }
    }

    /**
     * Créer connexion SQL Server
     *
     * @param string $host
     * @param string $database
     * @param string $user
     * @param string $password
     * @param int|null $port
     * @return \Aura\Sql\ExtendedPdo
     */
    private function createSQLServerConnection(
        string $host,
        string $database,
        string $user,
        string $password,
        ?int $port = null
    ) {
        // TODO: Créer PdoSQLServerHelper
        $serverName = $port ? "$host,$port" : $host;
        $dsn = "sqlsrv:Server=$serverName;Database=$database";

        try {
            return new \Aura\Sql\ExtendedPdo($dsn, $user, $password);
        } catch (\Exception $e) {
            $this->logger->error("SQL Server connection failed: " . $e->getMessage());
            throw new \Exception("SQL Server connection failed: " . $e->getMessage());
        }
    }

    /**
     * Générer clé unique pour le pool
     *
     * @param array $config
     * @return string
     */
    private function getPoolKey(array $config): string
    {
        return md5(json_encode([
            'driver' => $config['db_driver'],
            'host' => $config['db_host'],
            'port' => $config['db_port'] ?? null,
            'name' => $config['db_name'],
            'user' => $config['db_user']
        ]));
    }

    /**
     * Fermer toutes les connexions
     */
    public function closeAllConnections(): void
    {
        $count = count($this->connectionPool);
        $this->connectionPool = [];
        $this->logger->info("Closed $count database connections");
    }

    /**
     * Obtenir statistiques du pool
     *
     * @return array
     */
    public function getPoolStats(): array
    {
        return [
            'active_connections' => count($this->connectionPool),
            'connections' => array_keys($this->connectionPool)
        ];
    }
}
