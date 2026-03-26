<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RendezVousRepository::class)]
class RendezVous
{
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
}
