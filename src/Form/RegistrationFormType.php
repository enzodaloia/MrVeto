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
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Regex;
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
                    new Email(['message' => 'L\'adresse email "{{ value }}" n\'est pas valide.']),
                ],
            ])
            ->add('nom', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire.']),
                    new Length(['min' => 2, 'minMessage' => 'Le nom doit comporter au moins {{ limit }} caractères.']),
                ],
            ])
            ->add('prenom', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est obligatoire.']),
                    new Length(['min' => 2, 'minMessage' => 'Le prénom doit comporter au moins {{ limit }} caractères.']),
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
                    'attr' => [
                        'minlength' => 10,
                        'maxlength' => 12,
                    ],
                    'constraints' => [
                        new NotBlank(['message' => 'Le téléphone est obligatoire.']),
                        new Length([
                            'min' => 10,
                            'max' => 12,
                            'minMessage' => 'Le numéro de téléphone doit contenir au moins {{ limit }} caractères.',
                            'maxMessage' => 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères.',
                        ]),
                    ],
                ])
                ->add('adressecabinet', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank(['message' => 'L\'adresse du cabinet est obligatoire.'])],
                ])
                ->add('siret', TextType::class, [
                    'required' => true,
                    'constraints' => [
                        new NotBlank(['message' => 'Le numéro SIRET est obligatoire.']),
                        new Regex(['pattern' => '/^\d{14}$/', 'message' => 'Le SIRET doit comporter exactement 14 chiffres.']),
                    ],
                ])
                ->add('latitude', HiddenType::class, ['required' => false])
                ->add('longitude', HiddenType::class, ['required' => false]);
        }

        if ($type === 'utilisateur') {
            $builder
                ->add('datenaissance', DateType::class, [
                    'widget' => 'single_text',
                    'required' => true,
                    'attr' => [
                        'max' => (new \DateTime('-18 years'))->format('Y-m-d'),
                    ],
                    'constraints' => [
                        new NotBlank(['message' => 'La date de naissance est obligatoire.']),
                        new LessThanOrEqual([
                            'value' => '-18 years',
                            'message' => 'Vous devez avoir au moins 18 ans pour vous inscrire.',
                        ]),
                    ],
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
                    'attr' => [
                        'minlength' => 10,
                        'maxlength' => 12,
                    ],
                    'constraints' => [
                        new NotBlank(['message' => 'Le téléphone est obligatoire.']),
                        new Length([
                            'min' => 10,
                            'max' => 12,
                            'minMessage' => 'Le numéro de téléphone doit contenir au moins {{ limit }} caractères.',
                            'maxMessage' => 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères.',
                        ]),
                    ],
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
                        min: 8,
                        minMessage: 'Votre mot de passe doit comporter au moins {{ limit }} caractères.',
                        max: 4096,
                    ),
                    new \Symfony\Component\Validator\Constraints\Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
                        'message' => 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',
                    ]),
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
