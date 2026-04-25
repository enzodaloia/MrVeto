<?php

namespace App\Entity;

use App\Repository\CabinetUserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CabinetUserRepository::class)]
#[ORM\Table(name: 'cabinet_user')]
#[ORM\UniqueConstraint(name: 'uniq_cabinet_user_pair', fields: ['cabinet', 'user'])]
class CabinetUser
{
    public const ROLE_VETERINAIRE = 'VETERINAIRE';
    public const ROLE_SECRETAIRE = 'SECRETAIRE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'cabinetUsers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Cabinet $cabinet = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 32)]
    private ?string $roleInCabinet = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCabinet(): ?Cabinet
    {
        return $this->cabinet;
    }

    public function setCabinet(?Cabinet $cabinet): static
    {
        $this->cabinet = $cabinet;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRoleInCabinet(): ?string
    {
        return $this->roleInCabinet;
    }

    public function setRoleInCabinet(string $roleInCabinet): static
    {
        $this->roleInCabinet = $roleInCabinet;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
