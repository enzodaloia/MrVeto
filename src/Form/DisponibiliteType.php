<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DisponibiliteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('horaires', CollectionType::class, [
            'entry_type'    => HoraireType::class,
            'entry_options' => [
                'label' => false,
                'user'  => $options['user'],
            ],
            'allow_add'    => false,
            'allow_delete' => false,
            'label'        => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['user' => null]);
        $resolver->setAllowedTypes('user', ['null', User::class]);
    }
}