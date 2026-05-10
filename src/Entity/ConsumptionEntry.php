<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConsumptionEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConsumptionEntryRepository::class)]
#[ORM\Table(name: 'consumption_entry')]
#[ORM\Index(name: 'idx_consumption_user_date', columns: ['user_id', 'consumed_on'])]
class ConsumptionEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Food::class)]
    #[ORM\JoinColumn(name: 'food_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Food $food;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $quantityGrams;

    #[ORM\Column(name: 'consumed_on', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $consumedOn;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, Food $food, float $quantityGrams, \DateTimeImmutable $consumedOn)
    {
        $this->user = $user;
        $this->food = $food;
        $this->quantityGrams = (string) $quantityGrams;
        $this->consumedOn = $consumedOn;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getFood(): Food
    {
        return $this->food;
    }

    public function getQuantityGrams(): float
    {
        return (float) $this->quantityGrams;
    }

    public function getConsumedOn(): \DateTimeImmutable
    {
        return $this->consumedOn;
    }
}
