<?php

namespace App\EventSubscriber;

use App\Entity\Animal;
use App\Entity\Cabinet;
use App\Entity\CabinetUser;
use App\Entity\DayOfWork;
use App\Entity\Horaire;
use App\Entity\Jour;
use App\Entity\RendezVous;
use App\Entity\ResetPasswordRequest;
use App\Entity\Traitement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class SlugSubscriber
{
    private const RANDOM_SLUG_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789';

    public function __construct(private SluggerInterface $slugger)
    {
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->handleSlug($args->getObject(), $args->getObjectManager());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $manager = $args->getObjectManager();

        if ($this->handleSlug($entity, $manager)) {
            $manager->getUnitOfWork()->recomputeSingleEntityChangeSet(
                $manager->getClassMetadata($entity::class),
                $entity
            );
        }
    }

    private function handleSlug(object $entity, ObjectManager $manager): bool
    {
        if (!method_exists($entity, 'getSlug') || !method_exists($entity, 'setSlug')) {
            return false;
        }

        if ($entity->getSlug()) {
            return false;
        }

        $base = $this->resolveSlugBase($entity);
        if ($base !== null && $base !== '') {
            $slug = $this->slugger->slug($base)->lower()->toString();
        } else {
            $slug = $this->generateRandomSlug($entity);
        }

        if ($slug === '') {
            $slug = $this->generateRandomSlug($entity);
        }

        $entity->setSlug($this->ensureUniqueSlug($slug, $entity, $manager));

        return true;
    }

    private function resolveSlugBase(object $entity): ?string
    {
        if ($entity instanceof User) {
            $parts = array_values(array_filter([
                trim((string) $entity->getPrenom()),
                trim((string) $entity->getNom()),
            ], static fn (string $s): bool => $s !== ''));

            if ($parts !== []) {
                return implode('-', $parts);
            }

            $email = trim((string) $entity->getEmail());

            return $email !== '' ? $email : null;
        }

        if ($entity instanceof Animal) {
            return (string) $entity->getNom();
        }

        if ($entity instanceof Cabinet) {
            return (string) $entity->getNom();
        }

        if ($entity instanceof Traitement) {
            return (string) $entity->getLibelle();
        }

        if ($entity instanceof Jour) {
            return (string) $entity->getLibelle();
        }

        return null;
    }

    private function generateRandomSlug(object $entity): string
    {
        $prefix = match (true) {
            $entity instanceof RendezVous => 'rdv',
            $entity instanceof Horaire => 'horaire',
            $entity instanceof DayOfWork => 'dow',
            $entity instanceof CabinetUser => 'cu',
            $entity instanceof ResetPasswordRequest => 'reset',
            default => 'item',
        };

        return sprintf('%s-%s', $prefix, $this->randomAlphanumeric(6));
    }

    private function randomAlphanumeric(int $length): string
    {
        $chars = self::RANDOM_SLUG_CHARS;
        $max = \strlen($chars) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }

        return $out;
    }

    private function ensureUniqueSlug(string $slug, object $entity, ObjectManager $manager): string
    {
        $repository = $manager->getRepository($entity::class);
        $candidate = $slug;
        $suffix = 2;

        while (true) {
            $existing = $repository->findOneBy(['slug' => $candidate]);
            if (!$existing || $this->isSameEntity($existing, $entity)) {
                return $candidate;
            }

            if ($this->usesRandomSlug($entity)) {
                $candidate = $this->generateRandomSlug($entity);
            } else {
                $candidate = sprintf('%s-%d', $slug, $suffix);
                $suffix++;
            }
        }
    }

    private function usesRandomSlug(object $entity): bool
    {
        return !($entity instanceof User
            || $entity instanceof Animal
            || $entity instanceof Cabinet
            || $entity instanceof Traitement
            || $entity instanceof Jour);
    }

    private function isSameEntity(object $existing, object $entity): bool
    {
        if ($existing === $entity) {
            return true;
        }

        if (method_exists($existing, 'getId') && method_exists($entity, 'getId')) {
            $existingId = $existing->getId();
            $entityId = $entity->getId();

            return $existingId !== null && $entityId !== null && $existingId === $entityId;
        }

        return false;
    }
}
