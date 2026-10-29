<?php

declare(strict_types=1);

namespace App\Command;

use App\Datatable\DemoSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:demo:reset', 'Recreate the demo database with fresh sample data.')]
final readonly class ResetDemoCommand
{
    public function __construct(
        private DemoSeeder $seeder,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $counts = $this->seeder->reset();

        $rows = [];
        foreach ($counts as $table => $count) {
            $rows[] = [$table, number_format($count)];
        }

        $io->table(['Table', 'Rows'], $rows);
        $io->success('Demo database reset complete.');

        return Command::SUCCESS;
    }
}
