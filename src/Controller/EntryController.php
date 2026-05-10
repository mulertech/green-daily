<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ConsumptionEntry;
use App\Entity\User;
use App\Repository\ConsumptionEntryRepository;
use App\Repository\FoodRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EntryController extends AbstractController
{
    #[Route('/entries/new', name: 'app_entries_new', methods: ['GET', 'POST'])]
    public function new(Request $request, FoodRepository $foods, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $errors = [];
        $foodQuery = '';
        $quantityRaw = '';

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('new-entry', $token)) {
                $errors[] = 'Jeton CSRF invalide.';
            }

            $foodId = (int) $request->request->get('food_id');
            $foodQuery = trim((string) $request->request->get('food_query'));
            $quantityRaw = trim((string) $request->request->get('quantity_grams'));
            $quantity = (float) str_replace(',', '.', $quantityRaw);

            $food = $foodId > 0 ? $foods->find($foodId) : null;
            if (null === $food && '' !== $foodQuery) {
                $food = $foods->findOneBy(['nameFr' => $foodQuery]);
            }

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

        return $this->render('entries/new.html.twig', [
            'errors' => $errors,
            'food_query' => $foodQuery,
            'quantity' => $quantityRaw,
        ]);
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
