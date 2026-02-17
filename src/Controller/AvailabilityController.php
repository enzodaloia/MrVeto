<?php

namespace App\Controller;

use App\Entity\Horaire;
use App\Form\HoraireType;
use App\Repository\DayOfWorkRepository;
use App\Repository\HoraireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AvailabilityController extends AbstractController
{
    #[Route('/availability', name: 'app_availability', methods: ['GET'])]
    public function index(DayOfWorkRepository $dayOfWorkRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $daysOfWork = $dayOfWorkRepository->findBy(['user' => $user], ['id' => 'ASC']);


        return $this->render('availability/index.html.twig', [
            'daysOfWork' => $daysOfWork,
        ]);
    }

    #[Route('/availability/horaire/new', name: 'app_availability_horaire_new', methods: ['GET', 'POST'])]
    public function newHoraire(
        Request $request,
        EntityManagerInterface $em,
        HoraireRepository $horaireRepository
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $horaire = new Horaire();
        $form = $this->createForm(HoraireType::class, $horaire, [
            'user' => $user, // on va s'en servir pour filtrer les DayOfWork dans le form
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // sécurité : empêcher d'ajouter un horaire sur le DayOfWork d'un autre user
            if ($horaire->getDayOfWork()?->getUser() !== $user) {
                throw $this->createAccessDeniedException();
            }

            // anti-chevauchement
            if (
                $horaireRepository->existsOverlapForDayOfWork(
                    $horaire->getDayOfWork(),
                    $horaire->getStartTime(),
                    $horaire->getEndTime(),
                    $horaire->getId()
                )
            ) {
                $form->addError(new FormError('Ce créneau chevauche un horaire existant.'));
            } else {
                $em->persist($horaire);
                $em->flush();

                $this->addFlash('success', 'Créneau ajouté.');
                return $this->redirectToRoute('app_availability');
            }
        }

        return $this->render('availability/new_horaire.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
