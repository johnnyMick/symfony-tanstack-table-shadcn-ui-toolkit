<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use <?= $seeder_fqcn; ?>;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('<?= $command_name; ?>', 'Recreate demo database tables with fresh sample data.')]
final readonly class <?= $class_name; ?>
{
    public function __construct(
        private <?= $seeder_short; ?> $seeder,
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
