<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractType<User> */
final class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'Имейл', 'attr' => ['autocomplete' => 'email']])
            ->add('displayName', TextType::class, ['label' => 'Име (по желание)', 'required' => false, 'attr' => ['autocomplete' => 'nickname']])
            ->add('plainPassword', NewPasswordType::class, ['mapped' => false])
            ->add('agree', CheckboxType::class, [
                'mapped' => false,
                'label' => 'Приемам правилата за поверителност',
                'constraints' => [new Assert\IsTrue(message: 'За да създадеш профил, трябва да приемеш правилата за поверителност.')],
            ])
            // honeypot: people never see it, bots fill it in
            ->add('website', TextType::class, ['mapped' => false, 'required' => false, 'label' => 'Не попълвай това поле', 'attr' => ['tabindex' => '-1', 'autocomplete' => 'off']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
