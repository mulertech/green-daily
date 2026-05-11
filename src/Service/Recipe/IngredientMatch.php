<?php

declare(strict_types=1);

namespace App\Service\Recipe;

use App\Entity\Food;

final readonly class IngredientMatch
{
    public function __construct(
        public string $rawName,
        public float $grams,
        public ?string $note,
        public ?Food $food,
        public ?float $rank,
    ) {
    }

    public function isMatched(): bool
    {
        return null !== $this->food;
    }
}
