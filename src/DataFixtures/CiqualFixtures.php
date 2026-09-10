<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Command\ImportCiqualCommand;
use App\Repository\FoodRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Reloads the CIQUAL table after the database has been reset.
 *
 * The Postgres volume is named after the Compose project, which carries the PHP version: changing
 * version hands over a new, empty one. This fixture makes the development environment usable in a
 * single command, with no manual reimport.
 *
 * It calls the import command rather than copying the 3,186 foods: the source file is already
 * versioned in the repository, and a second copy of the same data would be a second place to fix
 * the day the CIQUAL table changes edition.
 */
final class CiqualFixtures extends Fixture
{
    private const string SOURCE = '/import/Table_Ciqual_2020.csv';

    /**
     * Above this number of foods, the CIQUAL table and its three thousand rows are there; below
     * it, only what the migrations insert remains.
     */
    private const int MIGRATION_FOODS_CEILING = 100;

    public function __construct(
        private readonly ImportCiqualCommand $import,
        private readonly FoodRepository $foods,
        private readonly string $projectDir,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Three thousand foods re-read for nothing on every load: stop as soon as the table
        // already carries the CIQUAL data. The import itself stays idempotent, what is saved
        // here is time.
        //
        // A threshold, and not "at least one food": on a fresh database, the migrations first
        // insert a handful of dietary supplements, values read off their label. Stopping at the
        // sight of those would leave the CIQUAL table absent, precisely the case this fixture
        // has to cover.
        if ($this->foods->count([]) > self::MIGRATION_FOODS_CEILING) {
            return;
        }

        $path = $this->projectDir.self::SOURCE;

        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('CIQUAL source file missing: %s', $path));
        }

        $this->import->run(new ArrayInput(['path' => $path]), new NullOutput());
    }
}
