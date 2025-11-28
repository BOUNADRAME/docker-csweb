<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Entity\Organization;

/**
 * Commande pour créer une nouvelle organisation
 *
 * Usage:
 *   php bin/console csweb:org:create --code=ANSD --name="ANSD Sénégal" --country=SN
 */
class OrganizationCreateCommand extends Command
{
    protected static $defaultName = 'csweb:org:create';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new organization (tenant)')
            ->setHelp('This command allows you to create a new organization for multi-tenant setup')
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Organization code (unique identifier)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Organization name')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Organization type', 'statistics_office')
            ->addOption('country', null, InputOption::VALUE_OPTIONAL, 'Country code (ISO 3166-1 alpha-2)')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Contact email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $code = $input->getOption('code');
        $name = $input->getOption('name');

        if (!$code || !$name) {
            $io->error('Organization code and name are required!');
            return Command::FAILURE;
        }

        // Vérifier si le code existe déjà
        $existing = $this->entityManager
            ->getRepository(Organization::class)
            ->findByCode($code);

        if ($existing) {
            $io->error("Organization with code '$code' already exists!");
            return Command::FAILURE;
        }

        // Créer l'organisation
        $organization = new Organization();
        $organization->setOrganizationCode($code);
        $organization->setOrganizationName($name);
        $organization->setOrganizationType($input->getOption('type'));
        $organization->setCountryCode($input->getOption('country'));
        $organization->setContactEmail($input->getOption('email'));

        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        $io->success([
            "Organization created successfully!",
            "ID: " . $organization->getId(),
            "Code: " . $organization->getOrganizationCode(),
            "Name: " . $organization->getOrganizationName()
        ]);

        return Command::SUCCESS;
    }
}
