<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Registered visitors: a list, and "delete" for when somebody asks to be removed.
 * Journals are private, so the admin sees only how many entries there are, never the notes.
 *
 * @extends AbstractCrudController<User>
 */
final class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Потребител')
            ->setEntityLabelInPlural('Потребители')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['email', 'displayName'])
            ->setPaginatorPageSize(50)
            ->setHelp(Crud::PAGE_INDEX, 'Посетителите се регистрират сами от сайта. Изтриването на потребител изтрива безвъзвратно и неговия дневник. Администратори се създават и премахват само с командата app:create-admin.');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DETAIL, Action::BATCH_DELETE)
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => $action->displayIf(fn (User $user) => !$user->isAdmin()));
    }

    public function configureFields(string $pageName): iterable
    {
        yield EmailField::new('email', 'Имейл');
        yield TextField::new('displayName', 'Име');
        yield BooleanField::new('admin', 'Администратор')->renderAsSwitch(false)->setSortable(false);
        yield AssociationField::new('journalEntries', 'Записи в дневника')->setSortable(false);
        yield DateTimeField::new('createdAt', 'Регистриран');
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->isAdmin()) {
            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
