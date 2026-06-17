<?php

namespace App\Command;

use App\Entity\ResetPasswordRequest;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:delete-archived-users',
    description: 'Supprime les utilisateurs qui sont archivés depuis plus de 10 ans',
)]
class DeleteArchivedUsersCommand extends Command
{
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(UserRepository $userRepository, EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenYearsAgo = new \DateTime('-10 years');

        // Find users where archivedAt <= 10 years ago
        $usersToDelete = $this->userRepository->createQueryBuilder('u')
            ->where('u.archivedAt IS NOT NULL')
            ->andWhere('u.archivedAt <= :tenYearsAgo')
            ->setParameter('tenYearsAgo', $tenYearsAgo)
            ->getQuery()
            ->getResult();

        if (empty($usersToDelete)) {
            $io->success('Aucun utilisateur archivé depuis plus de 10 ans n\'a été trouvé.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($usersToDelete as $user) {
            // Delete related ResetPasswordRequests manually if cascade isn't configured
            $resetPasswordRequests = $this->entityManager
                ->getRepository(ResetPasswordRequest::class)
                ->findBy(['user' => $user]);

            foreach ($resetPasswordRequests as $resetPasswordRequest) {
                $this->entityManager->remove($resetPasswordRequest);
            }

            $this->entityManager->remove($user);
            $count++;
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d utilisateur(s) archivé(s) depuis plus de 10 ans ont été supprimés avec succès.', $count));

        return Command::SUCCESS;
    }
}
