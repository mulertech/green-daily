<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RecipeSuggestion;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RecipeSuggester
{
    public function __construct(
        private DailyIntakeCalculator $calculator,
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private string $webhookUrl,
        private string $webhookToken,
    ) {
    }

    public function suggest(User $user, \DateTimeImmutable $day): RecipeSuggestion
    {
        $intake = $this->calculator->compute($user, $day);

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
        $this->em->persist($suggestion);

        $start = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $this->webhookUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, text/markdown, text/plain',
                    'X-Webhook-Token' => $this->webhookToken,
                ],
                'json' => [
                    'user_id' => $user->getId(),
                    'date' => $day->format('Y-m-d'),
                    'locale' => 'fr',
                    'constraints' => ['vegetarian' => true],
                    'remaining' => $payload,
                ],
                'timeout' => 60,
            ]);

            $status = $response->getStatusCode();
            $body = $response->getContent(false);
            $duration = (int) round((microtime(true) - $start) * 1000);

            if ($status >= 200 && $status < 300) {
                $markdown = $this->extractMarkdown($body);
                $suggestion->markSuccess($markdown, $duration);
            } else {
                $this->logger->warning('n8n webhook non-2xx', ['status' => $status, 'body' => $body]);
                $suggestion->markError(sprintf('Le service de recettes a répondu avec une erreur (%d).', $status), $duration);
            }
        } catch (TransportException|ExceptionInterface $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);
            $this->logger->error('n8n webhook failed', ['exception' => $e]);
            $suggestion->markError('Le service de recettes est injoignable. Réessaie dans un instant.', $duration);
        }

        $this->em->flush();

        return $suggestion;
    }

    private function extractMarkdown(string $body): string
    {
        $body = trim($body);
        if ('' === $body) {
            return '_Aucune recette renvoyée._';
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            foreach (['markdown', 'recipe', 'content', 'text', 'output'] as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key])) {
                    return $decoded[$key];
                }
            }
        }

        return $body;
    }
}
