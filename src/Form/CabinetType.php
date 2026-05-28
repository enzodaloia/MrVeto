<?php

namespace App\Form;

use App\Entity\Cabinet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class CabinetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom du cabinet'])
            ->add('adresse', TextType::class, ['required' => false])
            ->add('ville', TextType::class, ['required' => false])
            ->add('codePostal', TextType::class, ['required' => false])
            ->add('telephone', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Length([
                        'min' => 10,
                        'max' => 12,
                        'minMessage' => 'Le numéro de téléphone doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cabinet::class,
        ]);
    }
}
