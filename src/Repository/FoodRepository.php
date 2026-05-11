<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Food;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Food>
 */
class FoodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Food::class);
    }

    public function findOneByAlimCode(string $alimCode): ?Food
    {
        return $this->findOneBy(['alimCode' => $alimCode]);
    }

    /**
     * Find the best CIQUAL match for a free-form ingredient name.
     *
     * Uses OR semantics on the tsquery (each word optional) and ranks by coverage:
     * entries that contain MORE of the query terms rank higher. Falls back to the
     * shortest name on tie — typically the most generic CIQUAL entry.
     *
     * @return array{food: Food, rank: float}|null
     */
    public function findBestMatch(string $query): ?array
    {
        $tsquery = $this->buildOrTsQuery($query);
        if (null === $tsquery) {
            return null;
        }

        $sql = <<<'SQL'
            SELECT id, ts_rank(search_vector, to_tsquery('french_unaccent', :q)) AS rank
            FROM food
            WHERE search_vector @@ to_tsquery('french_unaccent', :q)
            ORDER BY rank DESC, length(name_fr) ASC
            LIMIT 1
        SQL;

        $row = $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, ['q' => $tsquery])
            ->fetchAssociative();

        if (false === $row) {
            return null;
        }

        $food = $this->find((int) $row['id']);
        if (null === $food) {
            return null;
        }

        return ['food' => $food, 'rank' => (float) $row['rank']];
    }

    private function buildOrTsQuery(string $query): ?string
    {
        $query = trim($query);
        if ('' === $query) {
            return null;
        }

        $terms = preg_split('/[\s,;]+/u', $query) ?: [];
        $clean = [];
        foreach ($terms as $term) {
            $term = preg_replace('/[^\p{L}\p{N}\-]/u', '', $term) ?? '';
            if (mb_strlen($term) >= 2) {
                $clean[] = $term;
            }
        }

        if ([] === $clean) {
            return null;
        }

        return implode(' | ', $clean);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function search(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT id, name_fr AS label
            FROM food
            WHERE search_vector @@ websearch_to_tsquery('french_unaccent', :q)
            ORDER BY ts_rank(search_vector, websearch_to_tsquery('french_unaccent', :q)) DESC,
                     length(name_fr) ASC
            LIMIT :lim
        SQL;

        $rows = $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, ['q' => $query, 'lim' => $limit], ['lim' => ParameterType::INTEGER])
            ->fetchAllAssociative();

        return array_map(
            static fn (array $r): array => ['id' => (int) $r['id'], 'label' => (string) $r['label']],
            $rows,
        );
    }
}
