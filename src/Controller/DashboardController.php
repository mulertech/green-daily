<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\MealType;
use App\Repository\ConsumptionEntryRepository;
use App\Service\DailyIntakeCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        DailyIntakeCalculator $calculator,
        ConsumptionEntryRepository $entries,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $today = new \DateTimeImmutable('today');

        $intake = $calculator->compute($user, $today);
        $todayEntries = $entries->findForDay($user, $today);

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'today' => $today,
            'intake' => $intake,
            'entries' => $todayEntries,
            'meal_types' => MealType::cases(),
        ]);
    }
}
