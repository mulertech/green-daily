<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RecipeSuggestion;
use App\Entity\User;
use App\Enum\MealType;
use App\Service\Recipe\RecipeCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RecipeSuggester
{
    public function __construct(
        private DailyIntakeCalculator $intakeCalculator,
        private RecipeCalculator $recipeCalculator,
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private string $webhookUrl,
        private string $webhookToken,
    ) {
    }

    public function suggest(User $user, \DateTimeImmutable $day, MealType $mealType): RecipeSuggestion
    {
        $intake = $this->intakeCalculator->compute($user, $day);

        $payload = [];
        foreach ($intake->ordered() as $stat) {
            $payload[$stat->code->value] = [
                'consumed' => round($stat->consumed, 2),
                'target' => $stat->target,
                'remaining' => $stat->remaining(),
                'unit' => $stat->code->unit(),
            ];
        }

        $suggestion = new RecipeSuggestion($user, $payload);
        $suggestion->setMealType($mealType);
        $this->em->persist($suggestion);

        $start = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $this->webhookUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Webhook-Token' => $this->webhookToken,
                ],
                'json' => [
                    'user_id' => $user->getId(),
                    'date' => $day->format('Y-m-d'),
                    'locale' => 'fr',
                    'constraints' => [
                        'vegetarian' => true,
                        'meal_type' => $mealType->value,
                    ],
                    'remaining' => $payload,
                ],
                'timeout' => 60,
            ]);

            $status = $response->getStatusCode();
            $body = $response->getContent(false);
            $duration = (int) round((microtime(true) - $start) * 1000);

            if ($status < 200 || $status >= 300) {
                $this->logger->warning('n8n webhook non-2xx', ['status' => $status, 'body' => $body]);
                $suggestion->markError(sprintf('Le service de recettes a répondu avec une erreur (%d).', $status), $duration);
                $this->em->flush();

                return $suggestion;
            }

            $recipePayload = $this->parseRecipePayload($body);

            if (null === $recipePayload) {
                $this->logger->warning('n8n webhook returned unparseable body', ['body' => $body]);
                $suggestion->markError('Le service de recettes a renvoyé une réponse invalide.', $duration);
                $this->em->flush();

                return $suggestion;
            }

            $computed = $this->recipeCalculator->compute($recipePayload);
            $suggestion->markSuccess($body, $duration);
            $suggestion->setRecipeData($computed->toArray());
        } catch (TransportException|ExceptionInterface $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);
            $this->logger->error('n8n webhook failed', ['exception' => $e]);
            $suggestion->markError('Le service de recettes est injoignable. Réessaie dans un instant.', $duration);
        }

        $this->em->flush();

        return $suggestion;
    }

    /**
     * Accept either the raw recipe JSON at top level, or wrapped under common keys.
     *
     * @return array<string, mixed>|null
     */
    private function parseRecipePayload(string $body): ?array
    {
        $body = trim($body);
        if ('' === $body) {
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return null;
        }

        if (isset($decoded['title']) || isset($decoded['ingredients'])) {
            /* @var array<string, mixed> $decoded */
            return $decoded;
        }

        foreach (['recipe', 'data', 'output'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                /** @var array<string, mixed> $inner */
                $inner = $decoded[$key];

                return $inner;
            }
        }

        return null;
    }
}
