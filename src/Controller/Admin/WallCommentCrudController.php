<?php

namespace App\Controller\Admin;

use App\Entity\WallComment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

/**
 * Comments of the wall ("Блог" in the footer of the site): everything that was written,
 * hidden comments included, with a switch to hide one or to bring it back.
 * Comments are never edited or deleted here.
 *
 * @extends AbstractCrudController<WallComment>
 */
final class WallCommentCrudController extends AbstractCrudController
{
    private const VIA = ['Форма' => WallComment::VIA_FORM, 'API' => WallComment::VIA_API];

    public static function getEntityFqcn(): string
    {
        return WallComment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Коментар')
            ->setEntityLabelInPlural('Коментари')
            ->setPageTitle(Crud::PAGE_EDIT, 'Коментар')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['name', 'message', 'os', 'ipHash'])
            ->setPaginatorPageSize(50)
            ->setHelp(Crud::PAGE_INDEX, 'Коментарите в блога се показват веднага, без одобрение. Скрит коментар изчезва от сайта заедно с отговорите си и може да бъде върнат оттук. Еднакъв код в колоната „Подател“ означава един и същ подател; търсенето по този код показва всичките му коментари.');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::DELETE, Action::DETAIL, Action::BATCH_DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isHidden', 'Скрит'))
            ->add(ChoiceFilter::new('via', 'Начин')->setChoices(self::VIA));
    }

    public function configureFields(string $pageName): iterable
    {
        $readOnly = ['disabled' => true];

        yield IdField::new('id', '№')->onlyOnIndex();
        yield DateTimeField::new('createdAt', 'Дата')->setFormTypeOptions($readOnly);
        yield TextField::new('name', 'Име')->setFormTypeOptions($readOnly);
        yield TextField::new('message', 'Коментар')->setMaxLength(120)->setFormTypeOptions($readOnly);
        yield TextField::new('os', 'Операционна система')->setFormTypeOptions($readOnly);
        yield ChoiceField::new('via', 'Начин')->setChoices(self::VIA)->setFormTypeOptions($readOnly);
        yield TextField::new('sender', 'Подател')->onlyOnIndex()->setSortable(false);
        yield TextField::new('userAgent', 'Браузър')->onlyOnForms()->setFormTypeOptions($readOnly);
        yield BooleanField::new('isHidden', 'Скрит')->renderAsSwitch(Crud::PAGE_INDEX === $pageName);
    }
}
