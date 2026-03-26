<?php

namespace App\Form;

use App\Entity\CabinetUser;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CabinetAssignmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'label' => 'Utilisateur (vétérinaire ou secrétaire)',
                'choice_label' => static function (User $user): string {
                    $firstName = $user->getPrenom() ?? '';
                    $lastName = $user->getNom() ?? '';
                    $name = trim($firstName . ' ' . $lastName);

                    return $name !== '' ? sprintf('%s (%s)', $name, $user->getEmail()) : (string) $user->getEmail();
                },
                'placeholder' => 'Sélectionner un utilisateur',
                'query_builder' => static function (EntityRepository $repository) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.roles LIKE :vetoRole OR u.roles LIKE :secretaryRole OR u.roles LIKE :secretaryLegacyRole OR u.roles LIKE :userRole')
                        ->setParameter('vetoRole', '%"ROLE_VETO"%')
                        ->setParameter('secretaryRole', '%"ROLE_SECRETARY"%')
                        ->setParameter('secretaryLegacyRole', '%"ROLE_SECRETAIRE"%')
                        ->setParameter('userRole', '%"ROLE_USER"%')
                        ->orderBy('u.nom', 'ASC')
                        ->addOrderBy('u.prenom', 'ASC');
                },
                'help' => 'Vous pouvez sélectionner un vétérinaire existant ou une secrétaire existante.',
            ])
            ->add('roleInCabinet', ChoiceType::class, [
                'choices' => [
                    'Vétérinaire' => CabinetUser::ROLE_VETERINAIRE,
                    'Secrétaire' => CabinetUser::ROLE_SECRETAIRE,
                ],
                'label' => 'Rôle dans le cabinet',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CabinetUser::class,
        ]);
    }
}
