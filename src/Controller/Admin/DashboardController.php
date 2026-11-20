<?php

namespace App\Controller\Admin;

use App\Daily\DailyPickService;
use App\Repository\QuestRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly QuestRepository $quests,
        private readonly DailyPickService $daily,
    ) {
    }

    public function index(): Response
    {
        $today = $this->daily->today();

        return $this->render('admin/dashboard.html.twig', [
            'published_count' => $this->quests->count(['isPublished' => true]),
            'draft_count' => $this->quests->count(['isPublished' => false]),
            'today' => $today,
            'today_challenges' => $this->daily->ensureForDate($today),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Занималник')
            ->setTranslationDomain('admin');
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setDateFormat('dd.MM.yyyy')
            ->setDateTimeFormat('dd.MM.yyyy HH:mm')
            ->setTimezone('Europe/Sofia');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addAssetMapperEntry('admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Табло', 'fa fa-house');

        yield MenuItem::section('Съдържание');
        yield MenuItem::linkTo(QuestCrudController::class, 'Куестове', 'fa fa-scroll');
        yield MenuItem::linkTo(PathCrudController::class, 'Пътеки', 'fa fa-signs-post');
        yield MenuItem::linkTo(TagCrudController::class, 'Тагове', 'fa fa-tags');

        yield MenuItem::section('Денят');
        yield MenuItem::linkToRoute('Предизвикателства на деня', 'fa fa-calendar-day', 'admin_daily_challenges_index');
        yield MenuItem::linkTo(JokeCrudController::class, 'Шега, виц, меме на деня', 'fa fa-face-laugh');
        yield MenuItem::linkTo(SignRoleCrudController::class, 'Роли за знака', 'fa fa-mask');

        yield MenuItem::section('Сайт');
        yield MenuItem::linkTo(UserCrudController::class, 'Потребители', 'fa fa-users');
        yield MenuItem::linkTo(SettingCrudController::class, 'Текстове на сайта', 'fa fa-feather');
        yield MenuItem::linkToUrl('Към сайта', 'fa fa-arrow-up-right-from-square', '/')->setLinkTarget('_blank');
    }
}
