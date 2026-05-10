<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Food;
use App\Entity\FoodNutrient;
use App\Enum\NutrientCode;
use App\Repository\FoodRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:ciqual:import', description: 'Import CIQUAL 2020 nutrient table (XLSX or CSV)')]
final class ImportCiqualCommand extends Command
{
    /**
     * Map our nutrient codes to CIQUAL column-name candidates and a multiplier
     * to bring the value into the canonical unit defined by NutrientCode::unit().
     *
     * For OMEGA3 we sum multiple columns (DHA + EPA, g → mg).
     *
     * @var array<string, array{columns: list<string>, factor: float, sum: bool}>
     */
    private const COLUMN_MAP = [
        'B12' => ['columns' => ['Vitamine B12 (µg/100 g)'], 'factor' => 1.0, 'sum' => false],
        'FE' => ['columns' => ['Fer (mg/100 g)'], 'factor' => 1.0, 'sum' => false],
        'ZN' => ['columns' => ['Zinc (mg/100 g)'], 'factor' => 1.0, 'sum' => false],
        'VITD' => ['columns' => ['Vitamine D (µg/100 g)'], 'factor' => 1.0, 'sum' => false],
        'OMEGA3' => [
            'columns' => [
                'AG 22:6 4c,7c,10c,13c,16c,19c (n-3) DHA (g/100 g)',
                'AG 20:5 5c,8c,11c,14c,17c (n-3) EPA (g/100 g)',
            ],
            'factor' => 1000.0,
            'sum' => true,
        ],
        'IODE' => ['columns' => ['Iode (µg/100 g)'], 'factor' => 1.0, 'sum' => false],
        'CA' => ['columns' => ['Calcium (mg/100 g)'], 'factor' => 1.0, 'sum' => false],
        'MG' => ['columns' => ['Magnésium (mg/100 g)'], 'factor' => 1.0, 'sum' => false],
        'VITA' => ['columns' => ['Rétinol (µg/100 g)'], 'factor' => 1.0, 'sum' => false],
        'SE' => ['columns' => ['Sélénium (µg/100 g)'], 'factor' => 1.0, 'sum' => false],
        'PROT' => ['columns' => ['Protéines, N x 6.25 (g/100 g)', 'Protéines, N x facteur de Jones (g/100 g)'], 'factor' => 1.0, 'sum' => false],
    ];

    private const COL_ALIM_CODE = 'alim_code';
    private const COL_NAME = 'alim_nom_fr';
    private const COL_GROUP = 'alim_grp_nom_fr';
    private const COL_SUBGROUP = 'alim_ssgrp_nom_fr';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FoodRepository $foods,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to CIQUAL XLSX or CSV file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('path');

        if (!is_file($path)) {
            $io->error(sprintf('File not found: %s', $path));

            return Command::FAILURE;
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray(null, false, false, false);
        if ([] === $rows) {
            $io->error('Empty spreadsheet.');

            return Command::FAILURE;
        }

        /** @var list<string> $header */
        $header = array_map(static fn ($v): string => trim((string) $v), $rows[0]);
        $colIndex = $this->resolveColumnIndexes($header, $io);
        if (null === $colIndex) {
            return Command::FAILURE;
        }

        $imported = 0;
        $batchSize = 200;
        $total = count($rows) - 1;

        $io->progressStart($total);

        for ($i = 1, $n = count($rows); $i < $n; ++$i) {
            $row = $rows[$i];
            $alimCode = $this->str($row, $colIndex[self::COL_ALIM_CODE] ?? null);
            $nameFr = $this->str($row, $colIndex[self::COL_NAME] ?? null);

            if ('' === $alimCode || '' === $nameFr) {
                $io->progressAdvance();
                continue;
            }

            $food = $this->foods->findOneByAlimCode($alimCode) ?? new Food($alimCode, $nameFr);
            $food->setNameFr($nameFr);
            $food->setGroupName($this->strOrNull($row, $colIndex[self::COL_GROUP] ?? null));
            $food->setSubGroupName($this->strOrNull($row, $colIndex[self::COL_SUBGROUP] ?? null));

            $this->em->persist($food);
            $this->em->flush();

            $existingNutrients = [];
            foreach ($food->getNutrients() as $fn) {
                $existingNutrients[$fn->getNutrientCode()->value] = $fn;
            }

            foreach (self::COLUMN_MAP as $codeStr => $cfg) {
                $code = NutrientCode::from($codeStr);
                $value = $this->extractValue($row, $colIndex, $cfg);
                if (null === $value) {
                    continue;
                }

                $value *= $cfg['factor'];

                if (isset($existingNutrients[$codeStr])) {
                    $existingNutrients[$codeStr]->setAmountPer100g($value);
                } else {
                    $this->em->persist(new FoodNutrient($food, $code, $value));
                }
            }

            ++$imported;

            if (0 === $imported % $batchSize) {
                $this->em->flush();
                $this->em->clear();
            }

            $io->progressAdvance();
        }

        $this->em->flush();
        $this->em->clear();
        $io->progressFinish();

        $io->success(sprintf('%d foods imported.', $imported));

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $header
     *
     * @return array<string, int>|null
     */
    private function resolveColumnIndexes(array $header, SymfonyStyle $io): ?array
    {
        $idx = [];
        foreach ($header as $i => $name) {
            $idx[$name] = $i;
        }

        $required = [self::COL_ALIM_CODE, self::COL_NAME];
        foreach ($required as $col) {
            if (!isset($idx[$col])) {
                $io->error(sprintf('Required column "%s" not found in header.', $col));
                $io->writeln('Available headers:');
                foreach (array_keys($idx) as $h) {
                    $io->writeln(' - '.$h);
                }

                return null;
            }
        }

        $io->section('Nutrient column resolution');
        foreach (self::COLUMN_MAP as $code => $cfg) {
            $matched = array_values(array_filter($cfg['columns'], static fn (string $c): bool => isset($idx[$c])));
            $missing = array_values(array_diff($cfg['columns'], $matched));
            if ([] === $matched) {
                $io->writeln(sprintf('  <error>✗ %s</error> — none of: %s', $code, implode(' | ', $cfg['columns'])));
            } elseif ([] !== $missing && $cfg['sum']) {
                $io->writeln(sprintf('  <comment>~ %s</comment> — partial: matched %s, missing %s', $code, implode(' + ', $matched), implode(' + ', $missing)));
            } else {
                $io->writeln(sprintf('  <info>✓ %s</info> — %s', $code, implode(' + ', $matched)));
            }
        }

        return $idx;
    }

    /**
     * @param array<int, mixed>                                      $row
     * @param array<string, int>                                     $colIndex
     * @param array{columns: list<string>, factor: float, sum: bool} $cfg
     */
    private function extractValue(array $row, array $colIndex, array $cfg): ?float
    {
        $sum = 0.0;
        $any = false;

        foreach ($cfg['columns'] as $col) {
            $i = $colIndex[$col] ?? null;
            if (null === $i) {
                continue;
            }
            $parsed = $this->parseNumber($row[$i] ?? null);
            if (null === $parsed) {
                continue;
            }
            $any = true;
            if ($cfg['sum']) {
                $sum += $parsed;
            } else {
                return $parsed;
            }
        }

        return $any ? $sum : null;
    }

    private function parseNumber(mixed $raw): ?float
    {
        if (null === $raw) {
            return null;
        }

        if (is_numeric($raw)) {
            return (float) $raw;
        }

        $s = trim((string) $raw);
        if ('' === $s || '-' === $s) {
            return null;
        }

        $lower = mb_strtolower($s);
        if ('traces' === $lower || 'tr.' === $lower || 'tr' === $lower) {
            return 0.0;
        }

        if (str_starts_with($s, '<')) {
            $n = $this->parseNumber(substr($s, 1));

            return null === $n ? null : $n / 2;
        }

        $s = str_replace([',', ' '], ['.', ''], $s);

        return is_numeric($s) ? (float) $s : null;
    }

    /** @param array<int, mixed> $row */
    private function str(array $row, ?int $i): string
    {
        if (null === $i) {
            return '';
        }

        return trim((string) ($row[$i] ?? ''));
    }

    /** @param array<int, mixed> $row */
    private function strOrNull(array $row, ?int $i): ?string
    {
        $v = $this->str($row, $i);

        return '' === $v ? null : $v;
    }
}
