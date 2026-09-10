<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Command\SeedRdaCommand;
use App\Repository\RdaRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * ANSES nutrient reference values, without which the dashboard has nothing to compare against.
 *
 * Their absence breaks nothing visible: the day's intake is displayed, and only the progress bars
 * stay at zero, for lack of a target. It is a silent failure, hence one more reason to load these
 * values along with the rest rather than relying on a command run by hand.
 */
final class RdaFixtures extends Fixture
{
    public function __construct(
        private readonly SeedRdaCommand $seed,
        private readonly RdaRepository $rda,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // The command empties the table before filling it: it is not replayed over values
        // already in place, which may have been adjusted locally.
        if (0 !== $this->rda->count([])) {
            return;
        }

        $this->seed->run(new ArrayInput([]), new NullOutput());
    }
}
