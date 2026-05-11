<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $datenaissance = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $codepostal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $siret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adressecabinet = null;

    // Reset / harmonisation
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $img = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isArchived = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $archivedAt = null;

    /**
     * @var Collection<int, DayOfWork>
     */
    #[ORM\OneToMany(targetEntity: DayOfWork::class, mappedBy: 'user')]
    private Collection $dayOfWorks;

    /**
     * @var Collection<int, Animal>
     */
    #[ORM\OneToMany(targetEntity: Animal::class, mappedBy: 'proprietaire', orphanRemoval: true)]
    private Collection $animals;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->token = bin2hex(random_bytes(32));
        $this->dayOfWorks = new ArrayCollection();
        $this->animals = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = array_values(array_filter(array_map(
            static fn (mixed $role): string => strtoupper((string) $role),
            $this->roles
        )));

        if (in_array('ROLE_SECRETAIRE', $roles, true)) {
            $roles = array_values(array_diff($roles, ['ROLE_SECRETAIRE']));
            $roles[] = 'ROLE_SECRETARY';
        }

        if (in_array('ROLE_SECRETARY', $roles, true)) {
            $roles = array_values(array_diff($roles, ['ROLE_USER']));
        } else {
            $roles[] = 'ROLE_USER';
        }

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): static
    {
        $normalizedRoles = [];
        foreach ($roles as $role) {
            $normalized = strtoupper((string) $role);
            if ($normalized === 'ROLE_SECRETAIRE') {
                $normalized = 'ROLE_SECRETARY';
            }

            if ($normalized !== '') {
                $normalizedRoles[] = $normalized;
            }
        }

        $normalizedRoles = array_values(array_unique($normalizedRoles));
        if (in_array('ROLE_SECRETARY', $normalizedRoles, true)) {
            $normalizedRoles = array_values(array_diff($normalizedRoles, ['ROLE_USER']));
        }

        $this->roles = $normalizedRoles;
        return $this;
    }

    public function getPassword(): ?string { return $this->password; }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', (string) $this->password);
        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void {}

    public function isVerified(): bool { return $this->isVerified; }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }


    public function getNom(): ?string { return $this->nom; }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string { return $this->prenom; }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getDatenaissance(): ?\DateTime { return $this->datenaissance; }

    public function setDatenaissance(?\DateTime $datenaissance): static
    {
        $this->datenaissance = $datenaissance;
        return $this;
    }

    public function getAdresse(): ?string { return $this->adresse; }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getTelephone(): ?string { return $this->telephone; }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getVille(): ?string { return $this->ville; }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;
        return $this;
    }

    public function getCodepostal(): ?string { return $this->codepostal; }

    public function setCodepostal(?string $codepostal): static
    {
        $this->codepostal = $codepostal;
        return $this;
    }

    public function getSiret(): ?string { return $this->siret; }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;
        return $this;
    }

    public function getAdressecabinet(): ?string { return $this->adressecabinet; }

    public function setAdressecabinet(?string $adressecabinet): static
    {
        $this->adressecabinet = $adressecabinet;
        return $this;
    }

    // DayOfWork
    public function getDayOfWorks(): Collection
    {
        return $this->dayOfWorks;
    }

    public function addDayOfWork(DayOfWork $dayOfWork): static
    {
        if (!$this->dayOfWorks->contains($dayOfWork)) {
            $this->dayOfWorks->add($dayOfWork);
            $dayOfWork->setUser($this);
        }
        return $this;
    }

    public function removeDayOfWork(DayOfWork $dayOfWork): static
    {
        if ($this->dayOfWorks->removeElement($dayOfWork)) {
            if ($dayOfWork->getUser() === $this) {
                $dayOfWork->setUser(null);
            }
        }
        return $this;
    }

    // Reset / harmonisation
    public function getToken(): ?string { return $this->token; }

    public function setToken(?string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getImg(): ?string { return $this->img; }

    public function setImg(?string $img): static
    {
        $this->img = $img;
        return $this;
    }

    public function getSlug(): ?string { return $this->slug; }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getLongitude(): ?string { return $this->longitude; }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getLatitude(): ?string { return $this->latitude; }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // Animals
    /**
     * @return Collection<int, Animal>
     */
    public function getAnimals(): Collection
    {
        return $this->animals;
    }

    public function addAnimal(Animal $animal): static
    {
        if (!$this->animals->contains($animal)) {
            $this->animals->add($animal);
            $animal->setProprietaire($this);
        }
        return $this;
    }

    public function removeAnimal(Animal $animal): static
    {
        if ($this->animals->removeElement($animal)) {
            if ($animal->getProprietaire() === $this) {
                $animal->setProprietaire(null);
            }
        }
        return $this;
    }

    public function isArchived(): bool
    {
        return $this->isArchived;
    }

    public function setIsArchived(bool $isArchived): static
    {
        $this->isArchived = $isArchived;

        return $this;
    }

    public function getArchivedAt(): ?\DateTimeInterface
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeInterface $archivedAt): static
    {
        $this->archivedAt = $archivedAt;

        return $this;
    }
}
