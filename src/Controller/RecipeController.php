<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\RecipeSuggester;
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
}
