<?php

namespace App\Controller\Admin;

use App\Entity\SignRole;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<SignRole> */
final class SignRoleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SignRole::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Роля за знака')
            ->setEntityLabelInPlural('Роли за знака на деня')
            ->setPageTitle(Crud::PAGE_NEW, 'Нова роля')
            ->setDefaultSort(['sortOrder' => 'ASC', 'id' => 'ASC'])
            ->setHelp(Crud::PAGE_INDEX, 'Посетителят може да избере роля като основа за своя знак на деня.');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Име')->setColumns(5);
        yield TextField::new('slug', 'Адрес (slug)')
            ->setColumns(5)
            ->setRequired(false)
            ->setHelp('Оставете празно — ще се създаде от името.');
        yield IntegerField::new('sortOrder', 'Подредба')
            ->setColumns(2)
            ->setHelp('По-малкото число е по-напред.');
    }
}
