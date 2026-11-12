<?php

namespace App\Controller\Admin;

use App\Entity\Tag;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<Tag> */
final class TagCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tag::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Таг')
            ->setEntityLabelInPlural('Тагове')
            ->setPageTitle(Crud::PAGE_NEW, 'Нов таг')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'slug']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Име')->setColumns(6);
        yield TextField::new('slug', 'Адрес (slug)')
            ->setColumns(6)
            ->setRequired(false)
            ->setHelp('Само латиница. Оставете празно — ще се създаде от името.');
    }
}
