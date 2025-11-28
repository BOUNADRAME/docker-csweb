<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Entity\Organization;
use AppBundle\Entity\DatabaseConnection;
use AppBundle\Service\DatabaseConnectionManager;

/**
 * Commande pour ajouter une connexion de base de données à une organisation
 *
 * Usage:
 *   php bin/console csweb:db:add --org=ANSD --name="POSTGRES_PROD" \
 *     --driver=pdo_pgsql --host=postgres --db=ansd_data --user=cspro --password=secret
 */
class DatabaseConnectionCreateCommand extends Command
{
    protected static $defaultName = 'csweb:db:add';

    private EntityManagerInterface $entityManager;
    private DatabaseConnectionManager $connectionManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        DatabaseConnectionManager $connectionManager
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->connectionManager = $connectionManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Add a database connection to an organization')
            ->setHelp('This command allows you to configure a database connection for an organization')
            ->addOption('org', null, InputOption::VALUE_REQUIRED, 'Organization code')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Connection name')
            ->addOption('driver', null, InputOption::VALUE_REQUIRED, 'Database driver (pdo_mysql, pdo_pgsql, sqlsrv)')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Database host')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Database port')
            ->addOption('db', null, InputOption::VALUE_REQUIRED, 'Database name')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Database username')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Database password')
            ->addOption('charset', null, InputOption::VALUE_OPTIONAL, 'Database charset', 'utf8mb4')
            ->addOption('default', null, InputOption::VALUE_NONE, 'Set as default connection for organization')
            ->addOption('test', null, InputOption::VALUE_NONE, 'Test connection after creation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Récupérer l'organisation
        $orgCode = $input->getOption('org');
        $organization = $this->entityManager
            ->getRepository(Organization::class)
            ->findByCode($orgCode);

        if (!$organization) {
            $io->error("Organization '$orgCode' not found!");
            return Command::FAILURE;
        }

        // Valider le driver
        $driver = $input->getOption('driver');
        $validDrivers = [
            DatabaseConnection::DRIVER_MYSQL,
            DatabaseConnection::DRIVER_POSTGRESQL,
            DatabaseConnection::DRIVER_SQLSERVER
        ];

        if (!in_array($driver, $validDrivers)) {
            $io->error("Invalid driver. Must be one of: " . implode(', ', $validDrivers));
            return Command::FAILURE;
        }

        // Créer la connexion
        $connection = new DatabaseConnection();
        $connection->setOrganization($organization);
        $connection->setConnectionName($input->getOption('name'));
        $connection->setDbDriver($driver);
        $connection->setDbHost($input->getOption('host'));
        $connection->setDbPort($input->getOption('port'));
        $connection->setDbName($input->getOption('db'));
        $connection->setDbUser($input->getOption('user'));
        $connection->setDbCharset($input->getOption('charset'));

        // Chiffrer le mot de passe
        $password = $input->getOption('password');
        $encryptedPassword = $this->connectionManager->encryptPassword($password);
        $connection->setDbPasswordEncrypted($encryptedPassword);

        // Définir comme connexion par défaut si demandé
        if ($input->getOption('default')) {
            $connection->setIsDefault(true);

            // Désactiver les autres connexions par défaut
            $otherDefaults = $this->entityManager
                ->getRepository(DatabaseConnection::class)
                ->findBy([
                    'organization' => $organization,
                    'isDefault' => true
                ]);

            foreach ($otherDefaults as $other) {
                $other->setIsDefault(false);
            }
        }

        $this->entityManager->persist($connection);
        $this->entityManager->flush();

        $io->success([
            "Database connection created successfully!",
            "ID: " . $connection->getId(),
            "Name: " . $connection->getConnectionName(),
            "Driver: " . $connection->getDbDriver(),
            "Host: " . $connection->getDbHost(),
            "Database: " . $connection->getDbName()
        ]);

        // Test de connexion si demandé
        if ($input->getOption('test')) {
            $io->section('Testing connection...');

            $result = $this->connectionManager->testConnection($connection);

            if ($result['success']) {
                $io->success($result['message']);
            } else {
                $io->error($result['message']);
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
