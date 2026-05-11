<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ConsumptionEntry;
use App\Entity\User;
use App\Repository\FoodRepository;
use App\Repository\RecipeSuggestionRepository;
use App\Service\RecipeSuggester;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class RecipeController extends AbstractController
{
    #[Route('/recipe/suggest', name: 'app_recipe_suggest', methods: ['POST'])]
    public function suggest(
        Request $request,
        RecipeSuggester $suggester,
        RateLimiterFactory $recipeSuggestLimiter,
    ): Response {
        if (!$this->isCsrfTokenValid('recipe-suggest', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();

        $limiter = $recipeSuggestLimiter->create('user-'.$user->getId());
        if (!$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Trop de demandes. Réessaie dans un moment.');

            return $this->redirectToRoute('app_home');
        }

        $suggestion = $suggester->suggest($user, new \DateTimeImmutable('today'));

        return $this->render('recipe/show.html.twig', [
            'suggestion' => $suggestion,
            'targets' => $suggestion->getTargetNutrients(),
        ]);
    }

    #[Route('/recipe/{id}/add-to-day', name: 'app_recipe_add_to_day', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addToDay(
        int $id,
        Request $request,
        RecipeSuggestionRepository $suggestions,
        FoodRepository $foods,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $suggestion = $suggestions->find($id);
        if (null === $suggestion || $suggestion->getUser() !== $user) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('recipe-add-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $data = $suggestion->getRecipeData();
        $ingredients = is_array($data['ingredients'] ?? null) ? $data['ingredients'] : [];

        $today = new \DateTimeImmutable('today');
        $added = 0;
        $skipped = 0;

        foreach ($ingredients as $ing) {
            if (!is_array($ing) || true !== ($ing['matched'] ?? false)) {
                ++$skipped;
                continue;
            }

            $foodId = isset($ing['food_id']) ? (int) $ing['food_id'] : 0;
            $grams = isset($ing['grams']) ? (float) $ing['grams'] : 0.0;

            if ($foodId <= 0 || $grams <= 0.0) {
                ++$skipped;
                continue;
            }

            $food = $foods->find($foodId);
            if (null === $food) {
                ++$skipped;
                continue;
            }

            $em->persist(new ConsumptionEntry($user, $food, $grams, $today));
            ++$added;
        }

        $em->flush();

        if ($added > 0) {
            $msg = sprintf('%d ingrédient%s ajouté%s à la journée.', $added, $added > 1 ? 's' : '', $added > 1 ? 's' : '');
            if ($skipped > 0) {
                $msg .= sprintf(' (%d non comptabilisé%s ignoré%s)', $skipped, $skipped > 1 ? 's' : '', $skipped > 1 ? 's' : '');
            }
            $this->addFlash('success', $msg);
        } else {
            $this->addFlash('error', 'Aucun ingrédient comptabilisable à ajouter.');
        }

        return $this->redirectToRoute('app_home');
    }
}
