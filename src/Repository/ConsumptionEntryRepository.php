<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ConsumptionEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConsumptionEntry>
 */
class ConsumptionEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsumptionEntry::class);
    }

    /** @return list<ConsumptionEntry> */
    public function findForDay(User $user, \DateTimeImmutable $day): array
    {
        /** @var list<ConsumptionEntry> $rows */
        $rows = $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.consumedOn = :day')
            ->setParameter('user', $user)
            ->setParameter('day', $day->setTime(0, 0))
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Sum of (amount_per_100g * quantity / 100) per nutrient for a user/day.
     *
     * @return array<string, float> nutrient_code => total amount
     */
    public function sumNutrientsForDay(User $user, \DateTimeImmutable $day): array
    {
        $sql = <<<'SQL'
            SELECT fn.nutrient_code AS code,
                   SUM(fn.amount_per_100g * e.quantity_grams / 100.0) AS total
            FROM consumption_entry e
            JOIN food_nutrient fn ON fn.food_id = e.food_id
            WHERE e.user_id = :user_id
              AND e.consumed_on = :day
            GROUP BY fn.nutrient_code
        SQL;

        $rows = $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, [
                'user_id' => $user->getId(),
                'day' => $day->format('Y-m-d'),
            ])
            ->fetchAllAssociative();

        $result = [];
        foreach ($rows as $r) {
            $result[(string) $r['code']] = (float) $r['total'];
        }

        return $result;
    }
}
