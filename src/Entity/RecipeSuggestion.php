<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RecipeSuggestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecipeSuggestionRepository::class)]
#[ORM\Table(name: 'recipe_suggestion')]
#[ORM\Index(name: 'idx_recipe_user_date', columns: ['user_id', 'requested_at'])]
class RecipeSuggestion
{
    public const STATUS_OK = 'ok';
    public const STATUS_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $requestedAt;

    /** @var array<string, array{consumed: float, target: float|null, remaining: float|null, unit: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $targetNutrients;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $responseMarkdown = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'recipe_data', type: Types::JSON, nullable: true)]
    private ?array $recipeData = null;

    #[ORM\Column(length: 16)]
    private string $status;

    #[ORM\Column(nullable: true)]
    private ?int $durationMs = null;

    /**
     * @param array<string, array{consumed: float, target: float|null, remaining: float|null, unit: string}> $targetNutrients
     */
    public function __construct(User $user, array $targetNutrients)
    {
        $this->user = $user;
        $this->requestedAt = new \DateTimeImmutable();
        $this->targetNutrients = $targetNutrients;
        $this->status = self::STATUS_OK;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    /** @return array<string, array{consumed: float, target: float|null, remaining: float|null, unit: string}> */
    public function getTargetNutrients(): array
    {
        return $this->targetNutrients;
    }

    public function getResponseMarkdown(): ?string
    {
        return $this->responseMarkdown;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isOk(): bool
    {
        return self::STATUS_OK === $this->status;
    }

    /** @return array<string, mixed>|null */
    public function getRecipeData(): ?array
    {
        return $this->recipeData;
    }

    /** @param array<string, mixed>|null $data */
    public function setRecipeData(?array $data): void
    {
        $this->recipeData = $data;
    }

    public function markSuccess(string $rawBody, int $durationMs): void
    {
        $this->status = self::STATUS_OK;
        $this->responseMarkdown = $rawBody;
        $this->durationMs = $durationMs;
    }

    public function markError(string $message, int $durationMs): void
    {
        $this->status = self::STATUS_ERROR;
        $this->responseMarkdown = $message;
        $this->durationMs = $durationMs;
    }
}
