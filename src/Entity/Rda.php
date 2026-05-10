<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NutrientCode;
use App\Enum\Sex;
use App\Repository\RdaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RdaRepository::class)]
#[ORM\Table(name: 'rda')]
#[ORM\Index(name: 'idx_rda_lookup', columns: ['nutrient_code', 'sex', 'age_min', 'age_max'])]
class Rda
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: NutrientCode::class)]
    private NutrientCode $nutrientCode;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: Sex::class, nullable: true)]
    private ?Sex $sex = null;

    #[ORM\Column]
    private int $ageMin;

    #[ORM\Column]
    private int $ageMax;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    private string $amount;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $source = null;

    public function __construct(
        NutrientCode $code,
        ?Sex $sex,
        int $ageMin,
        int $ageMax,
        float $amount,
        ?string $source = null,
    ) {
        $this->nutrientCode = $code;
        $this->sex = $sex;
        $this->ageMin = $ageMin;
        $this->ageMax = $ageMax;
        $this->amount = (string) $amount;
        $this->source = $source;
    }

    public function getNutrientCode(): NutrientCode
    {
        return $this->nutrientCode;
    }

    public function getSex(): ?Sex
    {
        return $this->sex;
    }

    public function getAgeMin(): int
    {
        return $this->ageMin;
    }

    public function getAgeMax(): int
    {
        return $this->ageMax;
    }

    public function getAmount(): float
    {
        return (float) $this->amount;
    }
}
