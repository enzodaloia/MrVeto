<?php

namespace App\Entity;

use App\Repository\DayOfWorkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DayOfWorkRepository::class)]
#[ORM\Table(
    name: 'day_of_work',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_user_jour', columns: ['user_id', 'jour_id'])
    ]
)]
class DayOfWork
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'dayOfWorks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Jour $jour = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isWorking = true;

    /**
     * @var Collection<int, Horaire>
     */
    #[ORM\OneToMany(mappedBy: 'dayOfWork', targetEntity: Horaire::class, orphanRemoval: true)]
    private Collection $horaires;

    public function __construct()
    {
        $this->horaires = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getJour(): ?Jour
    {
        return $this->jour;
    }

    public function setJour(?Jour $jour): static
    {
        $this->jour = $jour;
        return $this;
    }

    public function isWorking(): bool
    {
        return $this->isWorking;
    }

    public function setIsWorking(bool $isWorking): static
    {
        $this->isWorking = $isWorking;
        return $this;
    }

    /**
     * @return Collection<int, Horaire>
     */
    public function getHoraires(): Collection
    {
        return $this->horaires;
    }

    public function addHoraire(Horaire $horaire): static
    {
        if (!$this->horaires->contains($horaire)) {
            $this->horaires->add($horaire);
            $horaire->setDayOfWork($this);
        }

        return $this;
    }

    public function removeHoraire(Horaire $horaire): static
    {
        if ($this->horaires->removeElement($horaire)) {
            if ($horaire->getDayOfWork() === $this) {
                $horaire->setDayOfWork(null);
            }
        }

        return $this;
    }
}
