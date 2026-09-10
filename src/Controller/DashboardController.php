<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\MealType;
use App\Repository\ConsumptionEntryRepository;
use App\Repository\RdaRepository;
use App\Service\DailyIntakeCalculator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        DailyIntakeCalculator $calculator,
        ConsumptionEntryRepository $entries,
        RdaRepository $rdas,
        LoggerInterface $logger,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $today = new \DateTimeImmutable('today');

        $intake = $calculator->compute($user, $today);
        $todayEntries = $entries->findForDay($user, $today);

        // Without reference values the dashboard still renders: the day's intake is there and
        // only the bars stay at zero, for lack of a target. A page that looks normal while its
        // reference data is missing is the worst case, it does not report itself.
        $referencesMissing = 0 === $rdas->count([]);

        if ($referencesMissing) {
            $logger->warning('No nutrient reference value loaded: run app:rda:seed.');
        }

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'today' => $today,
            'intake' => $intake,
            'entries' => $todayEntries,
            'meal_types' => MealType::cases(),
            'references_missing' => $referencesMissing,
            // Distinct from the previous case: the reference values exist, but age and sex are
            // missing to pick the one that applies. This is fixed in the profile, not in a console.
            'profile_incomplete' => null === $user->getSex() || null === $user->getBirthDate(),
        ]);
    }
}
