<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** A new password with the site's rules (registration, reset, change). */
final class NewPasswordType extends AbstractType
{
    public const MIN_LENGTH = 8;

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'Парола',
            'attr' => ['autocomplete' => 'new-password'],
            'constraints' => [
                new Assert\NotBlank(message: 'Паролата е задължителна.'),
                // 4096: the longest password Symfony hashes
                new Assert\Length(min: self::MIN_LENGTH, max: 4096, minMessage: 'Паролата трябва да е поне {{ limit }} знака.'),
            ],
        ]);
    }

    public function getParent(): string
    {
        return PasswordType::class;
    }
}
