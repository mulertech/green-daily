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
        $age = $this->ageFor($user, $day);
        $sex = $user->getSex();

        $stats = [];
        foreach (NutrientCode::cases() as $code) {
            $target = null;
            if (null !== $sex && null !== $age) {
                $rda = $this->rdas->findApplicable($code, $sex, $age);
                $target = $rda?->getAmount();
            }

            $stats[$code->value] = new NutrientStat(
                $code,
                (float) ($consumed[$code->value] ?? 0.0),
                $target,
            );
        }

        return new DailyIntake($stats);
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
