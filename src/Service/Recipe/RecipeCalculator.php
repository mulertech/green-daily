<?php

declare(strict_types=1);

namespace App\Service\Recipe;

use App\Enum\NutrientCode;

final readonly class RecipeCalculator
{
    public function __construct(private IngredientMatcher $matcher)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function compute(array $payload): ComputedRecipe
    {
        $matches = [];
        /** @var list<array<string, mixed>> $ingredients */
        $ingredients = is_array($payload['ingredients'] ?? null) ? $payload['ingredients'] : [];
        foreach ($ingredients as $raw) {
            $name = isset($raw['name']) ? (string) $raw['name'] : '';
            $grams = isset($raw['grams']) ? (float) $raw['grams'] : 0.0;
            $note = isset($raw['note']) ? (string) $raw['note'] : null;

            if ('' === $name || $grams <= 0.0) {
                continue;
            }

            $matches[] = $this->matcher->match($name, $grams, $note);
        }

        $apports = [];
        foreach (NutrientCode::cases() as $code) {
            $apports[$code->value] = 0.0;
        }

        foreach ($matches as $match) {
            $food = $match->food;
            if (null === $food) {
                continue;
            }

            foreach ($food->getNutrients() as $fn) {
                $apports[$fn->getNutrientCode()->value] += $fn->getAmountPer100g() * $match->grams / 100.0;
            }
        }

        $apports = array_map(static fn (float $v): float => round($v, 2), $apports);

        return new ComputedRecipe(
            title: (string) ($payload['title'] ?? 'Recette suggérée'),
            servings: (int) ($payload['servings'] ?? 1),
            prepTimeMin: isset($payload['prep_time_min']) ? (int) $payload['prep_time_min'] : null,
            ingredients: $matches,
            steps: array_map(static fn ($s): string => (string) $s, array_values(is_array($payload['steps'] ?? null) ? $payload['steps'] : [])),
            rationale: (string) ($payload['rationale'] ?? ''),
            apports: $apports,
        );
    }
}
