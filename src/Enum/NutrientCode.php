<?php

declare(strict_types=1);

namespace App\Enum;

enum NutrientCode: string
{
    case B12 = 'B12';
    case FE = 'FE';
    case ZN = 'ZN';
    case VITD = 'VITD';
    case OMEGA3 = 'OMEGA3';
    case IODE = 'IODE';
    case CA = 'CA';
    case MG = 'MG';
    case VITA = 'VITA';
    case SE = 'SE';
    case PROT = 'PROT';

    public function label(): string
    {
        return match ($this) {
            self::B12 => 'Vitamine B12',
            self::FE => 'Fer',
            self::ZN => 'Zinc',
            self::VITD => 'Vitamine D',
            self::OMEGA3 => 'Oméga-3 (DHA+EPA)',
            self::IODE => 'Iode',
            self::CA => 'Calcium',
            self::MG => 'Magnésium',
            self::VITA => 'Vitamine A (rétinol)',
            self::SE => 'Sélénium',
            self::PROT => 'Protéines',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::B12, self::VITD, self::IODE, self::VITA, self::SE => 'µg',
            self::FE, self::ZN, self::CA, self::MG, self::OMEGA3 => 'mg',
            self::PROT => 'g',
        };
    }
}
