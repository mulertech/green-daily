<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NutrientCode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'food_nutrient')]
#[ORM\UniqueConstraint(name: 'uniq_food_nutrient', columns: ['food_id', 'nutrient_code'])]
#[ORM\Index(name: 'idx_food_nutrient_code', columns: ['nutrient_code'])]
class FoodNutrient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Food::class, inversedBy: 'nutrients')]
    #[ORM\JoinColumn(name: 'food_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Food $food;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: NutrientCode::class)]
    private NutrientCode $nutrientCode;

    #[ORM\Column(name: 'amount_per_100g', type: Types::DECIMAL, precision: 12, scale: 4)]
    private string $amountPer100g;

    public function __construct(Food $food, NutrientCode $code, float $amountPer100g)
    {
        $this->food = $food;
        $this->nutrientCode = $code;
        $this->amountPer100g = (string) $amountPer100g;
    }

    public function getFood(): Food
    {
        return $this->food;
    }

    public function getNutrientCode(): NutrientCode
    {
        return $this->nutrientCode;
    }

    public function getAmountPer100g(): float
    {
        return (float) $this->amountPer100g;
    }

    public function setAmountPer100g(float $value): void
    {
        $this->amountPer100g = (string) $value;
    }
}
