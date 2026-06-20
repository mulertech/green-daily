<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Food;
use App\Enum\NutrientCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
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
        $terms = $this->cleanTerms($query);

        return [] === $terms ? null : implode(' | ', $terms);
    }

    /**
     * Autocomplete: each typed word is treated as a prefix (AND between words).
     * Boosts entries whose name STARTS with the typed query (unaccent-insensitive).
     *
     * @return list<array{id: int, label: string}>
     */
    public function search(string $query, int $limit = 15): array
    {
        $tsquery = $this->buildPrefixAndTsQuery($query);
        if (null === $tsquery) {
            return [];
        }

        $namePrefix = trim($query).'%';

        $sql = <<<'SQL'
            SELECT id, name_fr AS label
            FROM food
            WHERE search_vector @@ to_tsquery('french_unaccent', :q)
            ORDER BY CASE WHEN unaccent(name_fr) ILIKE unaccent(:prefix) THEN 0 ELSE 1 END,
                     ts_rank(search_vector, to_tsquery('french_unaccent', :q)) DESC,
                     length(name_fr) ASC
            LIMIT :lim
        SQL;

        $rows = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                $sql,
                ['q' => $tsquery, 'prefix' => $namePrefix, 'lim' => $limit],
                ['lim' => ParameterType::INTEGER],
            )
            ->fetchAllAssociative();

        return array_map(
            static fn (array $r): array => ['id' => (int) $r['id'], 'label' => (string) $r['label']],
            $rows,
        );
    }

    /**
     * CIQUAL sub-groups considered as meat (land animal flesh + cured meats).
     * Plant-based "substituts de produits carnés" are deliberately excluded.
     */
    public const array MEAT_SUBGROUPS = [
        'viandes crues',
        'viandes cuites',
        'charcuteries et assimilés',
        'autres produits à base de viande',
    ];

    /**
     * Foods richest in a given nutrient, ranked by amount per 100 g (descending).
     *
     * @param bool|null $meat null = all foods, true = meat only, false = exclude meat
     *
     * @return list<array{id: int, name: string, group: ?string, amount: float}>
     */
    public function topByNutrient(NutrientCode $code, int $limit = 50, ?bool $meat = null): array
    {
        $params = ['code' => $code->value, 'lim' => $limit];
        $types = ['lim' => ParameterType::INTEGER];
        $meatFilter = '';

        if (true === $meat) {
            $meatFilter = 'AND f.sub_group_name IN (:meat)';
            $params['meat'] = self::MEAT_SUBGROUPS;
            $types['meat'] = ArrayParameterType::STRING;
        } elseif (false === $meat) {
            $meatFilter = 'AND (f.sub_group_name IS NULL OR f.sub_group_name NOT IN (:meat))';
            $params['meat'] = self::MEAT_SUBGROUPS;
            $types['meat'] = ArrayParameterType::STRING;
        }

        $sql = <<<SQL
            SELECT f.id, f.name_fr AS name, f.group_name AS group_name, fn.amount_per_100g AS amount
            FROM food_nutrient fn
            JOIN food f ON f.id = fn.food_id
            WHERE fn.nutrient_code = :code
              AND fn.amount_per_100g > 0
              $meatFilter
            ORDER BY fn.amount_per_100g DESC, length(f.name_fr) ASC
            LIMIT :lim
        SQL;

        $rows = $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, $params, $types)
            ->fetchAllAssociative();

        return array_map(
            static fn (array $r): array => [
                'id' => (int) $r['id'],
                'name' => (string) $r['name'],
                'group' => null !== $r['group_name'] ? (string) $r['group_name'] : null,
                'amount' => (float) $r['amount'],
            ],
            $rows,
        );
    }

    private function buildPrefixAndTsQuery(string $query): ?string
    {
        $terms = $this->cleanTerms($query);

        return [] === $terms ? null : implode(' & ', array_map(static fn (string $t): string => $t.':*', $terms));
    }

    /** @return list<string> */
    private function cleanTerms(string $query): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }

        $terms = preg_split('/[\s,;]+/u', $query) ?: [];
        $clean = [];
        foreach ($terms as $term) {
            $term = preg_replace('/[^\p{L}\p{N}\-]/u', '', $term) ?? '';
            if (mb_strlen($term) >= 2) {
                $clean[] = $term;
            }
        }

        return $clean;
    }
}
