<?php

namespace App\Controller\Admin;

use App\Daily\DailyJokeService;
use App\Entity\Joke;
use App\Service\JokeImageProcessor;
use App\Twig\UploadExtension;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Jokes, anecdotes and memes; one of them is shown on the home page every day.
 *
 * @extends AbstractCrudController<Joke>
 */
final class JokeCrudController extends AbstractCrudController
{
    public function __construct(
        #[Autowire('%app.joke_image_dir%')] private readonly string $imageDir,
        private readonly JokeImageProcessor $imageProcessor,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Joke::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Шега, виц или меме')
            ->setEntityLabelInPlural('Шеги, вицове и мемета')
            ->setPageTitle(Crud::PAGE_NEW, 'Нова шега, виц или меме')
            ->setPageTitle(Crud::PAGE_EDIT, 'Редакция')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['text', 'imageAlt'])
            ->setPaginatorPageSize(30)
            ->setHelp(Crud::PAGE_INDEX, \sprintf(
                'Всеки ден на началната страница се показва по един запис, избран на случаен принцип измежду активните. Един и същ запис не се повтаря в рамките на %d дни; ако записите са по-малко, се редуват всички, преди някой да се повтори.',
                DailyJokeService::NO_REPEAT_DAYS,
            ));
    }

    public function configureActions(Actions $actions): Actions
    {
        // everything is visible on the edit page; a separate read-only page isn't needed
        return $actions->disable(Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('type', 'Вид')->setChoices(array_flip(Joke::TYPE_LABELS)))
            ->add(BooleanFilter::new('isActive', 'Активен'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield ChoiceField::new('type', 'Вид')
            ->setChoices(array_flip(Joke::TYPE_LABELS))
            ->renderExpanded()
            ->setHelp('Определя заглавието на сайта: „Шега на деня“, „Виц на деня“ или „Меме на деня“.');

        yield TextField::new('preview', 'Съдържание')->onlyOnIndex()->setMaxLength(90);

        yield TextareaField::new('text', 'Текст')
            ->onlyOnForms()
            ->setNumOfRows(6)
            ->setRequired(false)
            ->setHelp('Новите редове се запазват. Може да остане празно, ако има изображение.');

        yield ImageField::new('imagePath', 'Изображение')
            ->setUploadDir($this->imageDir)
            ->setBasePath(rtrim(UploadExtension::JOKE_PREFIX, '/'))
            ->setUploadedFileNamePattern('[slug]-[uuid32].[extension]')
            ->mimeTypes('image/jpeg,image/png,image/webp', 'Изображението трябва да е JPG, PNG или WebP.')
            ->maxSize('8M', 'Изображението е твърде голямо (най-много {{ limit }} {{ suffix }}).')
            ->deleteReplacedFile()
            ->setRequired(false)
            ->setHelp('JPG, PNG или WebP. Автоматично се намалява до 1200 px.');

        yield TextField::new('imageAlt', 'Описание на изображението')
            ->onlyOnForms()
            ->setRequired(false)
            ->setHelp('Задължително, ако има изображение: какво се вижда и какво пише на него. Чете се от екранните четци.');

        yield BooleanField::new('isActive', 'Активен')
            ->renderAsSwitch(Crud::PAGE_INDEX === $pageName)
            ->setHelp('Само активните записи могат да бъдат избрани за деня.');

        yield DateField::new('lastShownOn', 'Последно показван')->onlyOnIndex();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);
        $this->imageProcessor->process($entityInstance->getImagePath());
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);
        $this->imageProcessor->process($entityInstance->getImagePath());
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $image = $entityInstance->getImagePath();

        parent::deleteEntity($entityManager, $entityInstance);

        if ($image) {
            (new Filesystem())->remove($this->imageDir.'/'.$image);
        }
    }
}
