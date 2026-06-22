<?php

namespace App\Entity;

use App\Repository\VetProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VetProfileRepository::class)]
class VetProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'vetProfile', targetEntity: User::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aPropos = null;

    /** @var Collection<int, Specialite> */
    #[ORM\ManyToMany(targetEntity: Specialite::class, inversedBy: 'vetProfiles', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'vet_profile_specialite')]
    private Collection $specialites;

    /** @var string[]|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $moyensPaiement = null;

    /** @var string[]|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $animauxAcceptes = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $dureeConsultation = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isUrgentiste = false;

    /** @var string[]|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $langues = null;

    public function __construct()
    {
        $this->specialites = new ArrayCollection();
    }

    // ─── Getters / Setters ────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getAPropos(): ?string { return $this->aPropos; }

    public function setAPropos(?string $aPropos): static
    {
        $this->aPropos = $aPropos;
        return $this;
    }

    /** @return Collection<int, Specialite> */
    public function getSpecialites(): Collection { return $this->specialites; }

    public function addSpecialite(Specialite $s): static
    {
        if (!$this->specialites->contains($s)) {
            $this->specialites->add($s);
        }
        return $this;
    }

    public function removeSpecialite(Specialite $s): static
    {
        $this->specialites->removeElement($s);
        return $this;
    }

    /** @return string[]|null */
    public function getMoyensPaiement(): ?array { return $this->moyensPaiement; }

    /** @param string[]|null $moyensPaiement */
    public function setMoyensPaiement(?array $moyensPaiement): static
    {
        $this->moyensPaiement = $moyensPaiement;
        return $this;
    }

    /** @return string[]|null */
    public function getAnimauxAcceptes(): ?array { return $this->animauxAcceptes; }

    /** @param string[]|null $animauxAcceptes */
    public function setAnimauxAcceptes(?array $animauxAcceptes): static
    {
        $this->animauxAcceptes = $animauxAcceptes;
        return $this;
    }

    public function getDureeConsultation(): ?string { return $this->dureeConsultation; }

    public function setDureeConsultation(?string $dureeConsultation): static
    {
        $this->dureeConsultation = $dureeConsultation;
        return $this;
    }

    public function isUrgentiste(): bool { return $this->isUrgentiste; }

    public function setIsUrgentiste(bool $isUrgentiste): static
    {
        $this->isUrgentiste = $isUrgentiste;
        return $this;
    }

    /** @return string[]|null */
    public function getLangues(): ?array { return $this->langues; }

    /** @param string[]|null $langues */
    public function setLangues(?array $langues): static
    {
        $this->langues = $langues;
        return $this;
    }
}
