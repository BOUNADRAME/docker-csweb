<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use AppBundle\Entity\Dictionary;
use AppBundle\Entity\Organization;

/**
 * DictionaryRepository
 */
class DictionaryRepository extends EntityRepository
{
    /**
     * Trouve tous les dictionnaires actifs d'une organisation
     *
     * @param Organization $organization
     * @return Dictionary[]
     */
    public function findByOrganization(Organization $organization)
    {
        return $this->createQueryBuilder('d')
            ->where('d.organization = :organization')
            ->andWhere('d.isActive = :active')
            ->setParameter('organization', $organization)
            ->setParameter('active', true)
            ->orderBy('d.dictionaryName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un dictionnaire par son nom et son organisation
     *
     * @param string $name
     * @param Organization $organization
     * @return Dictionary|null
     */
    public function findOneByNameAndOrganization(string $name, Organization $organization): ?Dictionary
    {
        return $this->createQueryBuilder('d')
            ->where('d.dictionaryName = :name')
            ->andWhere('d.organization = :organization')
            ->setParameter('name', $name)
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
