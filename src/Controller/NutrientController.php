<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\NutrientCode;
use App\Repository\FoodRepository;
use App\Service\DailyIntakeCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NutrientController extends AbstractController
{
    #[Route('/nutrients/{code}', name: 'app_nutrient_show', methods: ['GET'], requirements: ['code' => '[A-Z0-9]+'])]
    public function show(
        string $code,
        Request $request,
        DailyIntakeCalculator $calculator,
        FoodRepository $foods,
    ): Response {
        $nutrient = NutrientCode::tryFrom($code);
        if (null === $nutrient) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $today = new \DateTimeImmutable('today');

        $meatParam = (string) $request->query->get('meat', 'all');
        $meat = match ($meatParam) {
            'with' => true,
            'without' => false,
            default => null,
        };

        $stat = $calculator->compute($user, $today)->get($nutrient);

        return $this->render('nutrients/show.html.twig', [
            'nutrient' => $nutrient,
            'stat' => $stat,
            'foods' => $foods->topByNutrient($nutrient, 400, $meat),
            'meat_filter' => null === $meat ? 'all' : $meatParam,
        ]);
    }
}
