<?php

namespace App\Entity;

use App\Repository\AnimalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnimalRepository::class)]
class Animal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'animals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $proprietaire = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $espece = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $race = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateNaissance = null;

    #[ORM\Column(nullable: true)]
    private ?bool $vaccinAJour = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $poids = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $remarque = null;

    public function getId(): ?int { return $this->id; }

    public function getProprietaire(): ?User { return $this->proprietaire; }
    public function setProprietaire(?User $proprietaire): static
    {
        $this->proprietaire = $proprietaire;
        return $this;
    }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getEspece(): ?string { return $this->espece; }
    public function setEspece(?string $espece): static
    {
        $this->espece = $espece;
        return $this;
    }

    public function getRace(): ?string { return $this->race; }
    public function setRace(?string $race): static
    {
        $this->race = $race;
        return $this;
    }

    public function getDateNaissance(): ?\DateTime { return $this->dateNaissance; }
    public function setDateNaissance(?\DateTime $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;
        return $this;
    }

    public function isVaccinAJour(): ?bool { return $this->vaccinAJour; }
    public function setVaccinAJour(?bool $vaccinAJour): static
    {
        $this->vaccinAJour = $vaccinAJour;
        return $this;
    }

    public function getPoids(): ?string { return $this->poids; }
    public function setPoids(?string $poids): static
    {
        $this->poids = $poids;
        return $this;
    }

    public function getRemarque(): ?string { return $this->remarque; }
    public function setRemarque(?string $remarque): static
    {
        $this->remarque = $remarque;
        return $this;
    }
}
