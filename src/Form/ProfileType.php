<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\Sex;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<User>
 */
final class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sex', EnumType::class, [
                'class' => Sex::class,
                'choice_label' => fn (Sex $sex): string => $sex->label(),
                'placeholder' => '— choisir —',
                'required' => true,
                'label' => 'Sexe',
            ])
            ->add('birthDate', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'label' => 'Date de naissance',
                'input' => 'datetime_immutable',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
