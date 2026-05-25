<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

class UserAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Utilisateur' => 'ROLE_USER',
                    'Secrétaire' => 'ROLE_SECRETARY',
                    'Vétérinaire' => 'ROLE_VETO',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('isVerified', null, [
                'label' => 'Compte vétérinaire validé',
            ])
            ->add('nom', null, [
                'required' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom est obligatoire.',
                    ]),
                ],
            ])
            ->add('prenom', null, [
                'required' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le prénom est obligatoire.',
                    ]),
                ],
            ])
            ->add('datenaissance', \Symfony\Component\Form\Extension\Core\Type\DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'attr' => [
                    'max' => (new \DateTime('-18 years'))->format('Y-m-d'),
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'La date de naissance est obligatoire.',
                    ]),
                    new LessThanOrEqual([
                        'value' => '-18 years',
                        'message' => 'L\'utilisateur doit avoir au moins 18 ans.',
                    ]),
                ],
            ])
            ->add('adresse', null, [
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'L\'adresse est obligatoire.',
                    ]),
                ],
            ])
            ->add('telephone', null, [
                'required' => true,
                'attr' => [
                    'minlength' => 10,
                    'maxlength' => 12,
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le téléphone est obligatoire.',
                    ]),
                    new Length([
                        'min' => 10,
                        'max' => 12,
                        'minMessage' => 'Le numéro de téléphone doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('ville', null, [
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'La ville est obligatoire.',
                    ]),
                ],
            ])
            ->add('codepostal')
            ->add('siret')
            ->add('adressecabinet');

        $builder->get('roles')->addModelTransformer(
            new CallbackTransformer(
                fn($rolesArray) => $rolesArray[0] ?? null,
                fn($roleString) => $roleString ? [$roleString] : []
            )
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
