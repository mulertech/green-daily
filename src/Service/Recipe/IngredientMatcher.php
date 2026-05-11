<?php

declare(strict_types=1);

namespace App\Service\Recipe;

use App\Repository\FoodRepository;

final readonly class IngredientMatcher
{
    /**
     * Below this ts_rank value the match is rejected as too weak.
     * Lowered for OR-semantics matching: a recipe ingredient like "tofu ferme"
     * matches only "tofu" in CIQUAL ("Tofu nature, préemballé"), which gives
     * a fractional rank but is still a valid match.
     */
    private const float MIN_RANK = 0.005;

    public function __construct(private FoodRepository $foods)
    {
    }

    public function match(string $name, float $grams, ?string $note = null): IngredientMatch
    {
        $best = $this->foods->findBestMatch($name);

        if (null === $best || $best['rank'] < self::MIN_RANK) {
            return new IngredientMatch($name, $grams, $note, null, $best['rank'] ?? null);
        }

        return new IngredientMatch($name, $grams, $note, $best['food'], $best['rank']);
    }
}
