<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FoodRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FoodRepository::class)]
#[ORM\Table(name: 'food')]
#[ORM\UniqueConstraint(name: 'uniq_food_alim_code', columns: ['alim_code'])]
class Food
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16)]
    private string $alimCode;

    #[ORM\Column(length: 255)]
    private string $nameFr;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $groupName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subGroupName = null;

    /** @var Collection<int, FoodNutrient> */
    #[ORM\OneToMany(targetEntity: FoodNutrient::class, mappedBy: 'food', orphanRemoval: true, cascade: ['persist'])]
    private Collection $nutrients;

    public function __construct(string $alimCode, string $nameFr)
    {
        $this->alimCode = $alimCode;
        $this->nameFr = $nameFr;
        $this->nutrients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlimCode(): string
    {
        return $this->alimCode;
    }

    public function getNameFr(): string
    {
        return $this->nameFr;
    }

    public function setNameFr(string $nameFr): void
    {
        $this->nameFr = $nameFr;
    }

    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    public function setGroupName(?string $groupName): void
    {
        $this->groupName = $groupName;
    }

    public function getSubGroupName(): ?string
    {
        return $this->subGroupName;
    }

    public function setSubGroupName(?string $subGroupName): void
    {
        $this->subGroupName = $subGroupName;
    }

    /** @return Collection<int, FoodNutrient> */
    public function getNutrients(): Collection
    {
        return $this->nutrients;
    }
}
