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
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $proprietaire = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $espece = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $race = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $age = null;

    #[ORM\Column(nullable: true)]
    private ?bool $vaccinAJour = false;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $prochainVaccin = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $poids = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $slug = null;

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

    public function getAge(): ?string { return $this->age; }
    public function setAge(?string $age): static
    {
        $this->age = $age;
        return $this;
    }

    public function isVaccinAJour(): ?bool { 
        if ($this->prochainVaccin === null) {
            return false;
        }
        $now = new \DateTime();
        $now->setTime(0, 0, 0);
        return $this->prochainVaccin >= $now;
    }
    public function setVaccinAJour(?bool $vaccinAJour): static
    {
        $this->vaccinAJour = $vaccinAJour;
        return $this;
    }

    public function getProchainVaccin(): ?\DateTimeInterface
    {
        return $this->prochainVaccin;
    }

    public function setProchainVaccin(?\DateTimeInterface $prochainVaccin): static
    {
        $this->prochainVaccin = $prochainVaccin;

        return $this;
    }

    public function getPoids(): ?string { return $this->poids; }
    public function setPoids(?string $poids): static
    {
        $this->poids = $poids;
        return $this;
    }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }
}
