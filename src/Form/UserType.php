<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Utilisateur' => 'ROLE_USER',
                    'Vétérinaire' => 'ROLE_VETO',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'expanded' => true,
                'multiple' => true,
            ])
            ->add('password', PasswordType::class, [
                'mapped' => false,
                'required' => true,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotBlank(
                        message: 'Veuillez entrer un mot de passe',
                    ),
                    new \Symfony\Component\Validator\Constraints\Length(
                        min: 8,
                        minMessage: 'Votre mot de passe doit comporter au moins {{ limit }} caractères.',
                        max: 4096,
                    ),
                    new \Symfony\Component\Validator\Constraints\Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
                        'message' => 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',
                    ]),
                ],
            ])
            ->add('nom')
            ->add('prenom')
            ->add('datenaissance', \Symfony\Component\Form\Extension\Core\Type\DateType::class, [
                'widget' => 'single_text',
                'attr' => [
                    'max' => (new \DateTime('-18 years'))->format('Y-m-d'),
                ],
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\LessThanOrEqual([
                        'value' => '-18 years',
                        'message' => 'L\'utilisateur doit avoir au moins 18 ans.',
                    ]),
                ],
            ])
            ->add('adresse')
            ->add('telephone', null, [
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\Length([
                        'min' => 10,
                        'max' => 12,
                        'minMessage' => 'Le numéro de téléphone doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('ville')
            ->add('codepostal')
            ->add('siret')
            ->add('adressecabinet')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
