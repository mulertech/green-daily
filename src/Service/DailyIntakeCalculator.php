<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\NutrientCode;
use App\Repository\ConsumptionEntryRepository;
use App\Repository\RdaRepository;

final readonly class DailyIntakeCalculator
{
    public function __construct(
        private ConsumptionEntryRepository $entries,
        private RdaRepository $rdas,
    ) {
    }

    public function compute(User $user, \DateTimeImmutable $day): DailyIntake
    {
        $consumed = $this->entries->sumNutrientsForDay($user, $day);
        $targets = $this->targets($user, $day);

        $stats = [];
        foreach (NutrientCode::cases() as $code) {
            $stats[$code->value] = new NutrientStat(
                $code,
                (float) ($consumed[$code->value] ?? 0.0),
                $targets[$code->value],
            );
        }

        return new DailyIntake($stats);
    }

    /**
     * Recommended daily target per nutrient for the user's profile.
     *
     * @return array<string, ?float> nutrient_code => target amount (null if profile incomplete)
     */
    public function targets(User $user, \DateTimeImmutable $day): array
    {
        $age = $this->ageFor($user, $day);
        $sex = $user->getSex();

        $targets = [];
        foreach (NutrientCode::cases() as $code) {
            $target = null;
            if (null !== $sex && null !== $age) {
                $target = $this->rdas->findApplicable($code, $sex, $age)?->getAmount();
            }

            $targets[$code->value] = $target;
        }

        return $targets;
    }

    private function ageFor(User $user, \DateTimeImmutable $day): ?int
    {
        $birth = $user->getBirthDate();
        if (null === $birth) {
            return null;
        }

        return (int) $birth->diff($day)->y;
    }
}
