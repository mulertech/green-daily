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
