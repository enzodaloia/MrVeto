<?php

namespace App\Entity;

use App\Repository\HoraireRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HoraireRepository::class)]
#[Assert\Expression(
    "this.getMorningStart() === null or this.getMorningEnd() === null or this.getMorningStart() < this.getMorningEnd()",
    message: "L'heure de début de matinée doit être avant l'heure de fin."
)]
#[Assert\Expression(
    "this.getAfternoonStart() === null or this.getAfternoonEnd() === null or this.getAfternoonStart() < this.getAfternoonEnd()",
    message: "L'heure de début d'après-midi doit être avant l'heure de fin."
)]

class Horaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'horaires')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DayOfWork $dayOfWork = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $morningStart = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $morningEnd = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $afternoonStart = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $afternoonEnd = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $slug = null;

    public function getId(): ?int { return $this->id; }

    public function getDayOfWork(): ?DayOfWork { return $this->dayOfWork; }
    public function setDayOfWork(?DayOfWork $dayOfWork): static
    {
        $this->dayOfWork = $dayOfWork;
        return $this;
    }

    public function getMorningStart(): ?\DateTime { return $this->morningStart; }
    public function setMorningStart(?\DateTime $morningStart): static
    {
        $this->morningStart = $morningStart;
        return $this;
    }

    public function getMorningEnd(): ?\DateTime { return $this->morningEnd; }
    public function setMorningEnd(?\DateTime $morningEnd): static
    {
        $this->morningEnd = $morningEnd;
        return $this;
    }

    public function getAfternoonStart(): ?\DateTime { return $this->afternoonStart; }
    public function setAfternoonStart(?\DateTime $afternoonStart): static
    {
        $this->afternoonStart = $afternoonStart;
        return $this;
    }

    public function getAfternoonEnd(): ?\DateTime { return $this->afternoonEnd; }
    public function setAfternoonEnd(?\DateTime $afternoonEnd): static
    {
        $this->afternoonEnd = $afternoonEnd;
        return $this;
    }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }
}