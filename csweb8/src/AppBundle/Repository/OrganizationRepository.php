<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use AppBundle\Entity\Organization;

class OrganizationRepository extends EntityRepository
{
    public function findActiveOrganizations(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('o.organizationName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCode(string $code): ?Organization
    {
        return $this->findOneBy(['organizationCode' => $code]);
    }
}
