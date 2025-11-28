<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Organisation/Structure (Tenant)
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\OrganizationRepository")
 * @ORM\Table(name="mt_organizations",
 *     indexes={
 *         @ORM\Index(name="idx_org_code", columns={"organization_code"}),
 *         @ORM\Index(name="idx_active", columns={"is_active"})
 *     }
 * )
 * @ORM\HasLifecycleCallbacks
 */
class Organization
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=50, unique=true, name="organization_code")
     */
    private string $organizationCode;

    /**
     * @ORM\Column(type="string", length=255, name="organization_name")
     */
    private string $organizationName;

    /**
     * @ORM\Column(type="string", length=50, name="organization_type", nullable=true)
     */
    private ?string $organizationType = 'statistics_office';

    /**
     * @ORM\Column(type="string", length=2, name="country_code", nullable=true)
     */
    private ?string $countryCode = null;

    /**
     * @ORM\Column(type="string", length=255, name="contact_email", nullable=true)
     */
    private ?string $contactEmail = null;

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

    /**
     * @ORM\OneToMany(targetEntity="DatabaseConnection", mappedBy="organization", cascade={"persist", "remove"})
     */
    private Collection $databaseConnections;

    /**
     * @ORM\OneToMany(targetEntity="Dictionary", mappedBy="organization")
     */
    private Collection $dictionaries;

    public function __construct()
    {
        $this->databaseConnections = new ArrayCollection();
        $this->dictionaries = new ArrayCollection();
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

    public function getOrganizationCode(): string
    {
        return $this->organizationCode;
    }

    public function setOrganizationCode(string $organizationCode): self
    {
        $this->organizationCode = $organizationCode;
        return $this;
    }

    public function getOrganizationName(): string
    {
        return $this->organizationName;
    }

    public function setOrganizationName(string $organizationName): self
    {
        $this->organizationName = $organizationName;
        return $this;
    }

    public function getOrganizationType(): ?string
    {
        return $this->organizationType;
    }

    public function setOrganizationType(?string $organizationType): self
    {
        $this->organizationType = $organizationType;
        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): self
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): self
    {
        $this->contactEmail = $contactEmail;
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

    public function getDatabaseConnections(): Collection
    {
        return $this->databaseConnections;
    }

    public function addDatabaseConnection(DatabaseConnection $connection): self
    {
        if (!$this->databaseConnections->contains($connection)) {
            $this->databaseConnections[] = $connection;
            $connection->setOrganization($this);
        }
        return $this;
    }

    public function removeDatabaseConnection(DatabaseConnection $connection): self
    {
        if ($this->databaseConnections->removeElement($connection)) {
            if ($connection->getOrganization() === $this) {
                $connection->setOrganization(null);
            }
        }
        return $this;
    }

    public function getDictionaries(): Collection
    {
        return $this->dictionaries;
    }
}
