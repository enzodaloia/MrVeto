<?php

namespace App\Form;

use App\Entity\DayOfWork;
use App\Entity\Horaire;
use App\Entity\User;
use App\Repository\DayOfWorkRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TimeType;


class HoraireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder
        ->add('startTime', TimeType::class, ['widget' => 'single_text'])
        ->add('endTime', TimeType::class, ['widget' => 'single_text'])
        ->add('dayOfWork', EntityType::class, [
            'class' => DayOfWork::class,
            'choice_label' => fn (DayOfWork $dow) => $dow->getJour()->getLibelle(),
            'query_builder' => function (DayOfWorkRepository $repo) use ($options) {
                return $repo->createQueryBuilder('d')
                    ->innerJoin('d.jour', 'j')
                    ->addSelect('j')
                    ->andWhere('d.user = :u')
                    ->setParameter('u', $options['user'])
                    ->orderBy('j.ordre', 'ASC');
            },
            'placeholder' => 'Choisir un jour',
        ]);
}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Horaire::class,
            'user' => null,
        ]);

        $resolver->setAllowedTypes('user', ['null', User::class]);
    }
}
