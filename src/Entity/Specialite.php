<?php

namespace App\Entity;

use App\Repository\SpecialiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpecialiteRepository::class)]
#[ORM\Table(name: 'specialite')]
class Specialite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $slug;

    #[ORM\Column(length: 150)]
    private string $label;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPredefined = false;

    /** @var Collection<int, VetProfile> */
    #[ORM\ManyToMany(targetEntity: VetProfile::class, mappedBy: 'specialites')]
    private Collection $vetProfiles;

    public function __construct()
    {
        $this->vetProfiles = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): static { $this->label = $label; return $this; }

    public function isPredefined(): bool { return $this->isPredefined; }
    public function setIsPredefined(bool $v): static { $this->isPredefined = $v; return $this; }

    /** @return Collection<int, VetProfile> */
    public function getVetProfiles(): Collection { return $this->vetProfiles; }
}
