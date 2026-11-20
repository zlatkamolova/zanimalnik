<?php

namespace App\Form;

use App\Entity\JournalEntry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<JournalEntry> */
final class JournalEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('completedOn', DateType::class, [
                'label' => 'Кога',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['max' => (new \DateTimeImmutable('today'))->format('Y-m-d')],
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Бележка',
                'required' => false,
                'attr' => ['rows' => 5],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JournalEntry::class]);
    }
}
