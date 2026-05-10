<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Rda;
use App\Enum\NutrientCode;
use App\Enum\Sex;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:rda:seed', description: 'Seed ANSES adult RDA values')]
final class SeedRdaCommand extends Command
{
    private const SOURCE = 'ANSES 2016';

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    /**
     * Adult ANSES references. Iron differs pre/post menopause; calcium is +50mg post 65.
     * Format: [code, sex|null (any), ageMin, ageMax, amount].
     *
     * @return list<array{NutrientCode, ?Sex, int, int, float}>
     */
    private function rows(): array
    {
        return [
            [NutrientCode::B12, null, 18, 200, 4.0],
            [NutrientCode::FE, Sex::Male, 18, 200, 11.0],
            [NutrientCode::FE, Sex::Female, 18, 50, 16.0],
            [NutrientCode::FE, Sex::Female, 51, 200, 11.0],
            [NutrientCode::ZN, Sex::Male, 18, 200, 14.0],
            [NutrientCode::ZN, Sex::Female, 18, 200, 11.0],
            [NutrientCode::VITD, null, 18, 200, 15.0],
            [NutrientCode::OMEGA3, null, 18, 200, 250.0],
            [NutrientCode::IODE, null, 18, 200, 150.0],
            [NutrientCode::CA, null, 18, 65, 950.0],
            [NutrientCode::CA, null, 66, 200, 1000.0],
            [NutrientCode::MG, Sex::Male, 18, 200, 380.0],
            [NutrientCode::MG, Sex::Female, 18, 200, 300.0],
            [NutrientCode::VITA, Sex::Male, 18, 200, 750.0],
            [NutrientCode::VITA, Sex::Female, 18, 200, 650.0],
            [NutrientCode::SE, null, 18, 200, 70.0],
            [NutrientCode::PROT, Sex::Male, 18, 200, 65.0],
            [NutrientCode::PROT, Sex::Female, 18, 200, 55.0],
        ];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->em->createQuery('DELETE FROM '.Rda::class.' r')->execute();

        foreach ($this->rows() as [$code, $sex, $min, $max, $amount]) {
            $this->em->persist(new Rda($code, $sex, $min, $max, $amount, self::SOURCE));
        }

        $this->em->flush();

        $io->success(sprintf('%d RDA rows seeded.', count($this->rows())));

        return Command::SUCCESS;
    }
}
