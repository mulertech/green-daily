<?php

declare(strict_types=1);

namespace App\Command;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:ciqual:headers', description: 'Dump CIQUAL header row (raw column names) for mapping diagnostics')]
final class DumpCiqualHeadersCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to CIQUAL XLS/XLSX/CSV');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, false, false, false);
        $header = $rows[0] ?? [];

        foreach ($header as $i => $h) {
            $output->writeln(sprintf('[%2d] %s', $i, json_encode((string) $h, JSON_UNESCAPED_UNICODE)));
        }

        return Command::SUCCESS;
    }
}
