<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use AppBundle\Entity\DatabaseConnection;
use AppBundle\Entity\Organization;

class DatabaseConnectionRepository extends EntityRepository
{
    public function findDefaultForOrganization(Organization $organization): ?DatabaseConnection
    {
        return $this->findOneBy([
            'organization' => $organization,
            'isDefault' => true,
            'isActive' => true
        ]);
    }

    public function findActiveConnectionsForOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.organization = :org')
            ->andWhere('c.isActive = :active')
            ->setParameter('org', $organization)
            ->setParameter('active', true)
            ->orderBy('c.isDefault', 'DESC')
            ->addOrderBy('c.connectionName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
