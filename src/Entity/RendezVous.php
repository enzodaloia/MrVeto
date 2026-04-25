<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RendezVousRepository::class)]
class RendezVous
{
    public const ACTION_TYPE_CANCELLED = 'annule';
    public const ACTION_TYPE_RESCHEDULED = 'deplace';

    public const ACTION_BY_CLIENT = 'client';
    public const ACTION_BY_VETERINAIRE = 'veterinaire';
    public const ACTION_BY_SECRETAIRE = 'secretaire';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $veterinaire = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Animal $animal = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $dateHeure = null;

    #[ORM\Column(length: 50)]
    private string $statut = 'en_attente';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $remarque = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $previousDateHeure = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $lastActionType = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $lastActionByRole = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $lastActionAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getClient(): ?User { return $this->client; }
    public function setClient(?User $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getVeterinaire(): ?User { return $this->veterinaire; }
    public function setVeterinaire(?User $veterinaire): static
    {
        $this->veterinaire = $veterinaire;
        return $this;
    }

    public function getAnimal(): ?Animal { return $this->animal; }
    public function setAnimal(?Animal $animal): static
    {
        $this->animal = $animal;
        return $this;
    }

    public function getDateHeure(): ?\DateTime { return $this->dateHeure; }
    public function setDateHeure(\DateTime $dateHeure): static
    {
        $this->dateHeure = $dateHeure;
        return $this;
    }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;
        return $this;
    }

    public function getRemarque(): ?string { return $this->remarque; }
    public function setRemarque(?string $remarque): static
    {
        $this->remarque = $remarque;
        return $this;
    }

    public function getPreviousDateHeure(): ?\DateTime { return $this->previousDateHeure; }
    public function setPreviousDateHeure(?\DateTime $previousDateHeure): static
    {
        $this->previousDateHeure = $previousDateHeure;
        return $this;
    }

    public function getLastActionType(): ?string { return $this->lastActionType; }
    public function setLastActionType(?string $lastActionType): static
    {
        $this->lastActionType = $lastActionType;
        return $this;
    }

    public function getLastActionByRole(): ?string { return $this->lastActionByRole; }
    public function setLastActionByRole(?string $lastActionByRole): static
    {
        $this->lastActionByRole = $lastActionByRole;
        return $this;
    }

    public function getLastActionAt(): ?\DateTime { return $this->lastActionAt; }
    public function setLastActionAt(?\DateTime $lastActionAt): static
    {
        $this->lastActionAt = $lastActionAt;
        return $this;
    }
}
