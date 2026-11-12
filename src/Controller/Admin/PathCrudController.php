<?php

namespace App\Controller\Admin;

use App\Entity\Path;
use App\Entity\Quest;
use App\Repository\PathRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** @extends AbstractCrudController<Path> */
final class PathCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Path::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Пътека')
            ->setEntityLabelInPlural('Пътеки')
            ->setPageTitle(Crud::PAGE_NEW, 'Нова пътека')
            ->setDefaultSort(['sortOrder' => 'ASC', 'id' => 'ASC'])
            ->setSearchFields(null);
    }

    public function configureActions(Actions $actions): Actions
    {
        $sort = Action::new('sort', 'Подреди', 'fa fa-arrows-up-down')
            ->linkToCrudAction('sort')
            ->createAsGlobalAction();

        return $actions->add(Crud::PAGE_INDEX, $sort);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('sortOrder', '№')->onlyOnIndex();
        yield TextField::new('name', 'Име')->setColumns(6);
        yield TextField::new('slug', 'Адрес (slug)')
            ->setColumns(6)
            ->setRequired(false)
            ->setHelp('Само латиница, напр. „krivite-pateki“. Оставете празно — ще се създаде от името.');
        yield ChoiceField::new('symbol', 'Символ (печат)')
            ->setChoices(Path::SYMBOLS)
            ->setColumns(6);
        yield ColorField::new('color', 'Цвят')->setColumns(6);
        yield TextareaField::new('description', 'Описание')->setNumOfRows(4)->hideOnIndex();
    }

    public function createEntity(string $entityFqcn): Path
    {
        // new paths go to the end of the list
        $last = $this->container->get('doctrine')->getRepository(Path::class)->findOneBy([], ['sortOrder' => 'DESC']);

        return (new Path())->setSortOrder(($last?->getSortOrder() ?? 0) + 10);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $quests = $entityManager->getRepository(Quest::class)->count(['path' => $entityInstance]);
        if ($quests > 0) {
            $this->addFlash('danger', \sprintf(
                'Пътеката „%s“ не може да бъде изтрита: тя е основна за %d куест(а). Първо ги преместете в друга пътека.',
                $entityInstance->getName(),
                $quests,
            ));

            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    /** Drag & drop ordering page. */
    #[AdminRoute('/sort', name: 'sort', options: ['methods' => ['GET']])]
    public function sort(PathRepository $paths): Response
    {
        return $this->render('admin/path/sort.html.twig', ['paths' => $paths->findAllOrdered()]);
    }

    /** Receives the new order as JSON: {"ids": [3, 1, 2, 4]}. */
    #[AdminRoute('/sort/save', name: 'sort_save', options: ['methods' => ['POST']])]
    public function saveSort(Request $request, PathRepository $paths, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('path-sort', (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'Невалиден токен. Презаредете страницата.'], Response::HTTP_FORBIDDEN);
        }

        $ids = $request->toArray()['ids'] ?? [];
        $byId = [];
        foreach ($paths->findAll() as $path) {
            $byId[$path->getId()] = $path;
        }

        $position = 0;
        foreach ($ids as $id) {
            if (isset($byId[(int) $id])) {
                $byId[(int) $id]->setSortOrder($position += 10);
            }
        }
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }
}
