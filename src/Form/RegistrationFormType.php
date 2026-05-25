<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $type = $options['type'];

        $builder
            ->add('email', EmailType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est obligatoire.']),
                ],
            ])
            ->add('nom', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire.']),
                ],
            ])
            ->add('prenom', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est obligatoire.']),
                ],
            ]);

        if ($type === 'veterinaire') {
            $builder
                ->add('adresse', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank(['message' => 'L\'adresse est obligatoire.'])],
                ])
                ->add('ville', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank(['message' => 'La ville est obligatoire.'])],
                ])
                ->add('codepostal', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank(['message' => 'Le code postal est obligatoire.'])],
                ])
                ->add('telephone', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank(['message' => 'Le téléphone est obligatoire.'])],
                ])
                ->add('adressecabinet', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank(['message' => 'L\'adresse du cabinet est obligatoire.'])],
                ])
                ->add('siret', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank(['message' => 'Le numéro SIRET est obligatoire.'])],
                ])
                ->add('latitude', HiddenType::class, ['required' => false])
                ->add('longitude', HiddenType::class, ['required' => false]);
        }

        if ($type === 'utilisateur') {
            $builder
                ->add('datenaissance', DateType::class, [
                    'widget' => 'single_text',
                    'required' => true,
                    'constraints' => [new NotBlank(['message' => 'La date de naissance est obligatoire.'])],
                ])
                ->add('adresse', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank(['message' => 'L\'adresse est obligatoire.'])],
                ])
                ->add('ville', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank(['message' => 'La ville est obligatoire.'])],
                ])
                ->add('codepostal', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank(['message' => 'Le code postal est obligatoire.'])],
                ])
                ->add('telephone', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank(['message' => 'Le téléphone est obligatoire.'])],
                ]);
        }

        $builder
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'J’accepte toutes les conditions et les politiques de confidentialité.',
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter les conditions.',
                    ]),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['autocomplete' => 'new-password']
                ],
                'second_options' => [
                    'label' => 'Confirmation du mot de passe',
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez entrer un mot de passe',
                    ),
                    new Length(
                        min: 6,
                        minMessage: 'Minimum {{ limit }} caractères',
                        max: 4096,
                    ),
                ],
            ]);
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'type' => 'user',
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'registration_item',
        ]);
    }
}
