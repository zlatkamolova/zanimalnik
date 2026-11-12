<?php

namespace App\Controller\Admin;

use App\Entity\Quest;
use App\Service\CoverImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FileField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/** @extends AbstractCrudController<Quest> */
final class QuestCrudController extends AbstractCrudController
{
    public function __construct(
        #[Autowire('%app.quest_pdf_dir%')] private readonly string $pdfDir,
        #[Autowire('%app.quest_cover_dir%')] private readonly string $coverDir,
        private readonly CoverImageProcessor $coverProcessor,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Quest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Куест')
            ->setEntityLabelInPlural('Куестове')
            ->setPageTitle(Crud::PAGE_NEW, 'Нов куест')
            ->setPageTitle(Crud::PAGE_EDIT, fn (Quest $quest) => \sprintf('Редакция: %s', $quest->getTitle()))
            ->setDefaultSort(['publishedAt' => 'DESC', 'id' => 'DESC'])
            ->setSearchFields(['title', 'summary', 'slug'])
            ->setPaginatorPageSize(30);
    }

    public function configureActions(Actions $actions): Actions
    {
        $view = Action::new('viewOnSite', 'Виж на сайта', 'fa fa-arrow-up-right-from-square')
            ->linkToUrl(fn (Quest $quest) => $this->generateUrl('app_quest', ['slug' => $quest->getSlug()]))
            ->setHtmlAttributes(['target' => '_blank', 'rel' => 'noopener'])
            ->displayIf(fn (Quest $quest) => $quest->isVisible());

        // everything is visible on the edit page; a separate read-only page isn't needed
        return $actions
            ->disable(Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $view)
            ->add(Crud::PAGE_EDIT, $view);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('path', 'Основна пътека'))
            ->add(ChoiceFilter::new('difficulty', 'Трудност')->setChoices(array_flip(Quest::DIFFICULTY_LABELS)))
            ->add(BooleanFilter::new('isPublished', 'Публикуван'))
            ->add(EntityFilter::new('tags', 'Тагове'));
    }

    public function configureFields(string $pageName): iterable
    {
        $difficulty = fn () => ChoiceField::new('difficulty', 'Трудност')
            ->setChoices(array_flip(Quest::DIFFICULTY_LABELS))
            ->renderAsBadges([1 => 'success', 2 => 'warning', 3 => 'danger']);

        // ---- index ----
        yield TextField::new('title', 'Заглавие')->onlyOnIndex();
        yield AssociationField::new('path', 'Пътека')->onlyOnIndex();
        yield $difficulty()->onlyOnIndex();
        yield BooleanField::new('isPublished', 'Публикуван')->renderAsSwitch()->onlyOnIndex();
        yield BooleanField::new('isDailyEligible', 'За деня')->renderAsSwitch()->onlyOnIndex();
        yield DateTimeField::new('publishedAt', 'Публикуван на')->onlyOnIndex();

        // ---- forms ----
        yield FormField::addTab('Съдържание', 'fa fa-scroll');
        yield TextField::new('title', 'Заглавие')->onlyOnForms()->setColumns(8);
        yield TextField::new('slug', 'Адрес (slug)')
            ->onlyOnForms()
            ->setColumns(4)
            ->setRequired(false)
            ->setHelp('Само латиница. Оставете празно — ще се създаде от заглавието.');
        yield TextareaField::new('summary', 'Кратко описание')
            ->onlyOnForms()
            ->setNumOfRows(2)
            ->setHelp('1–2 изречения. Показва се в картите и в описанието за търсачките.');
        yield TextEditorField::new('body', 'Правила и описание')
            ->onlyOnForms()
            ->setNumOfRows(16)
            ->setTrixEditorConfig([
                'blockAttributes' => [
                    'default' => ['tagName' => 'p'],
                    // the editor's "heading" button produces sub-headings inside the quest
                    'heading1' => ['tagName' => 'h3'],
                ],
            ]);

        yield FormField::addTab('Параметри', 'fa fa-sliders');
        yield AssociationField::new('path', 'Основна пътека')->onlyOnForms()->setColumns(6);
        yield AssociationField::new('additionalPaths', 'Допълнителни пътеки')
            ->onlyOnForms()
            ->setColumns(6)
            ->setRequired(false)
            ->setHelp('За куестове на кръстопът.');
        yield $difficulty()->onlyOnForms()->setColumns(4)->renderExpanded();
        yield TextField::new('duration', 'Продължителност')
            ->onlyOnForms()
            ->setColumns(4)
            ->setHelp('Свободен текст, напр. „един ден“.');
        yield AssociationField::new('tags', 'Тагове')->onlyOnForms()->setColumns(4)->setRequired(false);
        yield TextareaField::new('materials', 'Нужни неща')
            ->onlyOnForms()
            ->setNumOfRows(2)
            ->setRequired(false);

        yield FormField::addTab('Файлове', 'fa fa-paperclip');
        yield ImageField::new('coverImagePath', 'Корица')
            ->onlyOnForms()
            ->setUploadDir($this->coverDir)
            ->setBasePath('/uploads/quests/covers')
            ->setUploadedFileNamePattern('[slug]-[uuid32].[extension]')
            ->mimeTypes('image/jpeg,image/png,image/webp', 'Корицата трябва да е JPG, PNG или WebP.')
            ->maxSize('8M', 'Изображението е твърде голямо (най-много {{ limit }} {{ suffix }}).')
            ->deleteReplacedFile()
            ->setRequired(false)
            ->setHelp('JPG, PNG или WebP. Автоматично се намалява до 1600 px.');
        yield FileField::new('pdfPath', 'Лист на куеста (PDF)')
            ->onlyOnForms()
            ->setUploadDir($this->pdfDir)
            ->setBasePath('/uploads/quests/pdf')
            ->setUploadedFileNamePattern('[slug]-[uuid32].pdf')
            ->mimeTypes('application/pdf', 'Файлът трябва да е PDF.')
            ->maxSize('10M', 'PDF файлът е твърде голям (най-много {{ limit }} {{ suffix }}).')
            ->deleteReplacedFile()
            ->setRequired(false)
            ->setHelp('Само PDF, до 10 MB. Посетителите го изтеглят свободно.');

        yield FormField::addTab('Публикуване', 'fa fa-eye');
        yield BooleanField::new('isPublished', 'Публикуван')
            ->onlyOnForms()
            ->setHelp('Чернова, докато не е отметнато.');
        yield DateTimeField::new('publishedAt', 'Дата на публикуване')
            ->onlyOnForms()
            ->setRequired(false)
            ->setHelp('Оставете празно — попълва се при първото публикуване. Бъдеща дата скрива куеста до тогава.');
        yield BooleanField::new('isDailyEligible', 'Може да бъде предизвикателство на деня')->onlyOnForms();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);
        $this->coverProcessor->process($entityInstance->getCoverImagePath());
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
        $oldCover = $original['coverImagePath'] ?? null;

        parent::updateEntity($entityManager, $entityInstance);

        if ($oldCover !== $entityInstance->getCoverImagePath()) {
            $this->coverProcessor->removeVariants($oldCover);
        }
        $this->coverProcessor->process($entityInstance->getCoverImagePath());
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $pdf = $entityInstance->getPdfPath();
        $cover = $entityInstance->getCoverImagePath();

        parent::deleteEntity($entityManager, $entityInstance);

        $filesystem = new Filesystem();
        if ($pdf) {
            $filesystem->remove($this->pdfDir.'/'.$pdf);
        }
        if ($cover) {
            $filesystem->remove($this->coverDir.'/'.$cover);
            $this->coverProcessor->removeVariants($cover);
        }
    }
}
