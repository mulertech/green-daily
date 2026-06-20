<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ConsumptionEntry;
use App\Entity\Food;
use App\Entity\User;
use App\Enum\NutrientCode;
use App\Repository\ConsumptionEntryRepository;
use App\Repository\FoodRepository;
use App\Service\DailyIntakeCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EntryController extends AbstractController
{
    #[Route('/entries/new', name: 'app_entries_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        FoodRepository $foods,
        EntityManagerInterface $em,
        DailyIntakeCalculator $calculator,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $errors = [];
        $foodQuery = '';
        $quantityRaw = '';
        $foodId = 0;
        $preview = null;

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('new-entry', $token)) {
                $errors[] = 'Jeton CSRF invalide.';
            }

            $action = (string) $request->request->get('action');
            $foodId = (int) $request->request->get('food_id');
            $foodQuery = trim((string) $request->request->get('food_query'));
            $quantityRaw = trim((string) $request->request->get('quantity_grams'));
            $quantity = (float) str_replace(',', '.', $quantityRaw);

            $food = $foodId > 0 ? $foods->find($foodId) : null;
            if (null === $food && '' !== $foodQuery) {
                $food = $foods->findOneBy(['nameFr' => $foodQuery]);
            }
            $foodId = $food?->getId() ?? 0;

            if ('preview' === $action) {
                if (null === $food) {
                    $errors[] = 'Sélectionnez un aliment pour voir ses apports.';
                } elseif ([] === $errors) {
                    $preview = $this->buildPreview(
                        $food,
                        $quantity > 0.0 ? $quantity : 100.0,
                        $calculator,
                        $user,
                    );
                }
            } else {
                if (null === $food) {
                    $errors[] = 'Aliment introuvable. Sélectionnez-le dans la liste.';
                }
                if ($quantity <= 0.0 || $quantity > 5000.0) {
                    $errors[] = 'Quantité invalide (1–5000 g).';
                }

                if ([] === $errors && null !== $food) {
                    $em->persist(new ConsumptionEntry($user, $food, $quantity, new \DateTimeImmutable('today')));
                    $em->flush();
                    $this->addFlash('success', sprintf('Ajouté : %s (%.0f g)', $food->getNameFr(), $quantity));

                    return $this->redirectToRoute('app_home');
                }
            }
        }

        return $this->render('entries/new.html.twig', [
            'errors' => $errors,
            'food_query' => $foodQuery,
            'quantity' => $quantityRaw,
            'food_id' => $foodId,
            'preview' => $preview,
        ]);
    }

    /**
     * Nutrients provided by a food for a given quantity, with the share of the user's daily target.
     *
     * @return array{
     *     food: string,
     *     quantity: float,
     *     rows: list<array{label: string, unit: string, amount: float, target: ?float, percent: ?int}>
     * }
     */
    private function buildPreview(Food $food, float $quantity, DailyIntakeCalculator $calculator, User $user): array
    {
        $targets = $calculator->targets($user, new \DateTimeImmutable('today'));

        $byCode = [];
        foreach ($food->getNutrients() as $nutrient) {
            $byCode[$nutrient->getNutrientCode()->value] = $nutrient->getAmountPer100g();
        }

        $rows = [];
        foreach (NutrientCode::cases() as $code) {
            $per100g = $byCode[$code->value] ?? 0.0;
            if ($per100g <= 0.0) {
                continue;
            }

            $amount = $per100g * $quantity / 100.0;
            $target = $targets[$code->value];
            $percent = (null !== $target && $target > 0.0) ? (int) round($amount / $target * 100) : null;

            $rows[] = [
                'label' => $code->label(),
                'unit' => $code->unit(),
                'amount' => $amount,
                'target' => $target,
                'percent' => $percent,
            ];
        }

        return ['food' => $food->getNameFr(), 'quantity' => $quantity, 'rows' => $rows];
    }

    #[Route('/entries/{id}/delete', name: 'app_entries_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        Request $request,
        ConsumptionEntryRepository $entries,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $entry = $entries->find($id);
        if (null === $entry || $entry->getUser() !== $user) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('delete-entry-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($entry);
        $em->flush();
        $this->addFlash('success', 'Aliment supprimé.');

        return $this->redirectToRoute('app_home');
    }
}
