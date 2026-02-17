<?php

namespace App\Entity;

use App\Repository\HoraireRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: HoraireRepository::class)]
#[Assert\Expression(
    "this.getStartTime() < this.getEndTime()",
    message: "L'heure de début doit être avant l'heure de fin."
)]
class Horaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'horaires')]
    #[ORM\JoinColumn(nullable: false)]
    private ?DayOfWork $dayOfWork = null;

    #[Assert\NotNull]
    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $startTime = null;

    #[Assert\NotNull]
    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $endTime = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDayOfWork(): ?DayOfWork
    {
        return $this->dayOfWork;
    }

    public function setDayOfWork(?DayOfWork $dayOfWork): static
    {
        $this->dayOfWork = $dayOfWork;

        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface$startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTime
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface$endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }
}
