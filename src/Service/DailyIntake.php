<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\NutrientCode;

final readonly class DailyIntake
{
    /**
     * @param array<string, NutrientStat> $stats
     */
    public function __construct(public array $stats)
    {
    }

    public function get(NutrientCode $code): NutrientStat
    {
        return $this->stats[$code->value] ?? new NutrientStat($code, 0.0, null);
    }

    /** @return list<NutrientStat> */
    public function ordered(): array
    {
        $order = NutrientCode::cases();

        return array_map(fn (NutrientCode $c): NutrientStat => $this->get($c), $order);
    }
}
