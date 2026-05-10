<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Rda;
use App\Enum\NutrientCode;
use App\Enum\Sex;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rda>
 */
class RdaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rda::class);
    }

    public function findApplicable(NutrientCode $code, Sex $sex, int $age): ?Rda
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.nutrientCode = :code')
            ->andWhere('r.sex = :sex OR r.sex IS NULL')
            ->andWhere('r.ageMin <= :age')
            ->andWhere('r.ageMax >= :age')
            ->orderBy('r.sex', 'DESC')
            ->setParameter('code', $code)
            ->setParameter('sex', $sex)
            ->setParameter('age', $age)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /** @return list<Rda> */
    public function allForProfile(Sex $sex, int $age): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.sex = :sex OR r.sex IS NULL')
            ->andWhere('r.ageMin <= :age')
            ->andWhere('r.ageMax >= :age')
            ->setParameter('sex', $sex)
            ->setParameter('age', $age);

        /** @var list<Rda> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
