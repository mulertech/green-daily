<?php

declare(strict_types=1);

namespace App\Service\Recipe;

final readonly class ComputedRecipe
{
    /**
     * @param list<IngredientMatch> $ingredients
     * @param list<string>          $steps
     * @param array<string, float>  $apports     nutrient code => total
     */
    public function __construct(
        public string $title,
        public int $servings,
        public ?int $prepTimeMin,
        public array $ingredients,
        public array $steps,
        public string $rationale,
        public array $apports,
    ) {
    }

    /** @return list<IngredientMatch> */
    public function matched(): array
    {
        return array_values(array_filter($this->ingredients, static fn (IngredientMatch $m): bool => $m->isMatched()));
    }

    /** @return list<IngredientMatch> */
    public function unmatched(): array
    {
        return array_values(array_filter($this->ingredients, static fn (IngredientMatch $m): bool => !$m->isMatched()));
    }

    /** @return array{title: string, servings: int, prep_time_min: ?int, ingredients: list<array{name: string, grams: float, note: ?string, matched: bool, food_id: ?int, food_label: ?string, rank: ?float}>, steps: list<string>, rationale: string, apports: array<string, float>} */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'servings' => $this->servings,
            'prep_time_min' => $this->prepTimeMin,
            'ingredients' => array_map(static fn (IngredientMatch $m): array => [
                'name' => $m->rawName,
                'grams' => $m->grams,
                'note' => $m->note,
                'matched' => $m->isMatched(),
                'food_id' => $m->food?->getId(),
                'food_label' => $m->food?->getNameFr(),
                'rank' => $m->rank,
            ], $this->ingredients),
            'steps' => $this->steps,
            'rationale' => $this->rationale,
            'apports' => $this->apports,
        ];
    }
}
