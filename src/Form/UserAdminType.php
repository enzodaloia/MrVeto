<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserAdminType extends AbstractType
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
                'multiple' => false,
            ])
            ->add('isVerified')
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
            ->add('datenaissance')
            ->add('adresse')
            ->add('telephone')
            ->add('ville')
            ->add('codepostal')
            ->add('siret')
            ->add('adressecabinet');

        if (!$options['is_edit']) {
            $builder->add('password');
        }

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