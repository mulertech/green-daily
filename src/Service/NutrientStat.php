<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\NutrientCode;

final readonly class NutrientStat
{
    public function __construct(
        public NutrientCode $code,
        public float $consumed,
        public ?float $target,
    ) {
    }

    public function percent(): ?int
    {
        if (null === $this->target || $this->target <= 0.0) {
            return null;
        }

        return (int) round(($this->consumed / $this->target) * 100);
    }

    public function remaining(): ?float
    {
        if (null === $this->target) {
            return null;
        }

        return max(0.0, $this->target - $this->consumed);
    }
}
