<?php

namespace App\Controller\Admin;

use App\Entity\Setting;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Editable site texts (the "За сайта" page). The rows are created by fixtures/migrations;
 * the admin can only edit them.
 *
 * @extends AbstractCrudController<Setting>
 */
final class SettingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Setting::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Текст')
            ->setEntityLabelInPlural('Текстове на сайта')
            ->setPageTitle(Crud::PAGE_EDIT, fn (Setting $setting) => $setting->getLabel())
            ->setDefaultSort(['key' => 'ASC'])
            ->setSearchFields(null);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->setPermission(Action::EDIT, 'ROLE_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('label', 'Текст');
            yield DateTimeField::new('updatedAt', 'Променен');

            return;
        }

        $setting = $this->getContext()?->getEntity()->getInstance();

        if ($setting instanceof Setting && $setting->isRichText()) {
            yield TextEditorField::new('value', $setting->getLabel())
                ->setNumOfRows(14)
                ->setTrixEditorConfig(['blockAttributes' => [
                    'default' => ['tagName' => 'p'],
                    'heading1' => ['tagName' => 'h3'],
                ]]);
        } else {
            yield TextField::new('value', $setting?->getLabel() ?? 'Стойност');
        }
    }
}
