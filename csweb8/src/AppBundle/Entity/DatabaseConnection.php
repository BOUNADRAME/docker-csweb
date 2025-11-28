<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Connexion de Base de Données pour une Organisation
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\DatabaseConnectionRepository")
 * @ORM\Table(name="mt_database_connections",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="uk_org_conn_name", columns={"organization_id", "connection_name"})
 *     },
 *     indexes={
 *         @ORM\Index(name="idx_org_conn_driver", columns={"db_driver"}),
 *         @ORM\Index(name="idx_org_conn_default", columns={"organization_id", "is_default"})
 *     }
 * )
 * @ORM\HasLifecycleCallbacks
 */
class DatabaseConnection
{
    public const DRIVER_MYSQL = 'pdo_mysql';
    public const DRIVER_POSTGRESQL = 'pdo_pgsql';
    public const DRIVER_SQLSERVER = 'sqlsrv';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="Organization", inversedBy="databaseConnections")
     * @ORM\JoinColumn(name="organization_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private Organization $organization;

    /**
     * @ORM\Column(type="string", length=100, name="connection_name")
     */
    private string $connectionName;

    /**
     * @ORM\Column(type="string", length=20, name="db_driver")
     */
    private string $dbDriver;

    /**
     * @ORM\Column(type="string", length=255, name="db_host")
     */
    private string $dbHost;

    /**
     * @ORM\Column(type="integer", name="db_port", nullable=true)
     */
    private ?int $dbPort = null;

    /**
     * @ORM\Column(type="string", length=255, name="db_name")
     */
    private string $dbName;

    /**
     * @ORM\Column(type="string", length=255, name="db_user")
     */
    private string $dbUser;

    /**
     * Mot de passe chiffré (AES-256)
     * @ORM\Column(type="blob", name="db_password_encrypted")
     */
    private $dbPasswordEncrypted;

    /**
     * @ORM\Column(type="string", length=20, name="db_charset", options={"default"="utf8mb4"})
     */
    private string $dbCharset = 'utf8mb4';

    /**
     * Options de connexion additionnelles (SSL, etc.)
     * @ORM\Column(type="json", name="connection_options", nullable=true)
     */
    private ?array $connectionOptions = null;

    /**
     * @ORM\Column(type="boolean", name="is_default", options={"default"=false})
     */
    private bool $isDefault = false;

    /**
     * @ORM\Column(type="boolean", name="is_active", options={"default"=true})
     */
    private bool $isActive = true;

    /**
     * @ORM\Column(type="datetime", name="created_time")
     */
    private \DateTime $createdTime;

    /**
     * @ORM\Column(type="datetime", name="modified_time")
     */
    private \DateTime $modifiedTime;

    public function __construct()
    {
        $this->createdTime = new \DateTime();
        $this->modifiedTime = new \DateTime();
    }

    /**
     * @ORM\PreUpdate
     */
    public function setModifiedTimeValue(): void
    {
        $this->modifiedTime = new \DateTime();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;
        return $this;
    }

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    public function setConnectionName(string $connectionName): self
    {
        $this->connectionName = $connectionName;
        return $this;
    }

    public function getDbDriver(): string
    {
        return $this->dbDriver;
    }

    public function setDbDriver(string $dbDriver): self
    {
        $this->dbDriver = $dbDriver;
        return $this;
    }

    public function getDbHost(): string
    {
        return $this->dbHost;
    }

    public function setDbHost(string $dbHost): self
    {
        $this->dbHost = $dbHost;
        return $this;
    }

    public function getDbPort(): ?int
    {
        return $this->dbPort;
    }

    public function setDbPort(?int $dbPort): self
    {
        $this->dbPort = $dbPort;
        return $this;
    }

    public function getDbName(): string
    {
        return $this->dbName;
    }

    public function setDbName(string $dbName): self
    {
        $this->dbName = $dbName;
        return $this;
    }

    public function getDbUser(): string
    {
        return $this->dbUser;
    }

    public function setDbUser(string $dbUser): self
    {
        $this->dbUser = $dbUser;
        return $this;
    }

    public function getDbPasswordEncrypted()
    {
        return $this->dbPasswordEncrypted;
    }

    public function setDbPasswordEncrypted($dbPasswordEncrypted): self
    {
        $this->dbPasswordEncrypted = $dbPasswordEncrypted;
        return $this;
    }

    public function getDbCharset(): string
    {
        return $this->dbCharset;
    }

    public function setDbCharset(string $dbCharset): self
    {
        $this->dbCharset = $dbCharset;
        return $this;
    }

    public function getConnectionOptions(): ?array
    {
        return $this->connectionOptions;
    }

    public function setConnectionOptions(?array $connectionOptions): self
    {
        $this->connectionOptions = $connectionOptions;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedTime(): \DateTime
    {
        return $this->createdTime;
    }

    public function getModifiedTime(): \DateTime
    {
        return $this->modifiedTime;
    }

    /**
     * Get DSN string for this connection
     */
    public function getDsn(): string
    {
        $port = $this->dbPort ? ";port={$this->dbPort}" : '';

        switch ($this->dbDriver) {
            case self::DRIVER_MYSQL:
                return "mysql:host={$this->dbHost}{$port};dbname={$this->dbName};charset={$this->dbCharset}";

            case self::DRIVER_POSTGRESQL:
                return "pgsql:host={$this->dbHost}{$port};dbname={$this->dbName}";

            case self::DRIVER_SQLSERVER:
                return "sqlsrv:Server={$this->dbHost}{$port};Database={$this->dbName}";

            default:
                throw new \Exception("Unsupported database driver: {$this->dbDriver}");
        }
    }
}
