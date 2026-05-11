<?php

declare(strict_types=1);

namespace App\Enum;

enum MealType: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Snack = 'snack';
    case Dinner = 'dinner';

    public function label(): string
    {
        return match ($this) {
            self::Breakfast => 'Petit-déjeuner',
            self::Lunch => 'Déjeuner',
            self::Snack => 'Goûter',
            self::Dinner => 'Dîner',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Breakfast => '🥐',
            self::Lunch => '☀️',
            self::Snack => '🍪',
            self::Dinner => '🌙',
        };
    }
}
