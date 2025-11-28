<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Dictionary - Représente un dictionnaire CSPro associé à une organisation
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\DictionaryRepository")
 * @ORM\Table(name="mt_dict_org_mapping")
 */
class Dictionary
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=100, unique=true)
     */
    private $dictionaryName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $description;

    /**
     * @ORM\ManyToOne(targetEntity="Organization", inversedBy="dictionaries")
     * @ORM\JoinColumn(name="organization_id", referencedColumnName="id", nullable=false)
     */
    private $organization;

    /**
     * @ORM\ManyToOne(targetEntity="DatabaseConnection")
     * @ORM\JoinColumn(name="db_connection_id", referencedColumnName="id", nullable=true)
     */
    private $dbConnection;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $updatedAt;

    /**
     * @ORM\Column(type="boolean", options={"default": true})
     */
    private $isActive = true;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->isActive = true;
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDictionaryName(): ?string
    {
        return $this->dictionaryName;
    }

    public function setDictionaryName(string $dictionaryName): self
    {
        $this->dictionaryName = $dictionaryName;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;
        return $this;
    }

    public function getDbConnection(): ?DatabaseConnection
    {
        return $this->dbConnection;
    }

    public function setDbConnection(?DatabaseConnection $dbConnection): self
    {
        $this->dbConnection = $dbConnection;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
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
}
