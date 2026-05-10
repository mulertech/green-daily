<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\FoodRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class FoodSearchController extends AbstractController
{
    #[Route('/api/foods/search', name: 'api_foods_search', methods: ['GET'])]
    public function search(Request $request, FoodRepository $foods): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));

        if (mb_strlen($q) < 2) {
            return new JsonResponse([]);
        }

        return new JsonResponse($foods->search($q, 15));
    }
}
