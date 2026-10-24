<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Copies skeleton / kit files into the application.
 *
 * @internal
 */
final class SkeletonInstaller
{
    public function __construct(
        private readonly string $bundleRoot,
    ) {
    }

    public static function create(): self
    {
        return new self(\dirname(__DIR__, 2));
    }

    public function skeletonRoot(): string
    {
        $root = $this->bundleRoot.'/templates/skeleton';
        if (!is_dir($root)) {
            throw new \RuntimeException(\sprintf('Skeleton directory not found: "%s".', $root));
        }

        return realpath($root) ?: $root;
    }

    public function kitRoot(string $kit): string
    {
        $root = $this->skeletonRoot().'/kits/'.$kit;
        if (!is_dir($root)) {
            throw new \RuntimeException(\sprintf('Kit "%s" not found: "%s".', $kit, $root));
        }

        return realpath($root) ?: $root;
    }

    /**
     * @param list<string> $kits Kit names under templates/skeleton/kits/
     *
     * @return array{created: list<string>, updated: list<string>, skipped: list<string>}
     */
    public function installKits(Generator $generator, array $kits, bool $force): array
    {
        $paths = [];
        foreach ($kits as $kit) {
            foreach ($this->listRelativeFiles($this->kitRoot($kit)) as $relative) {
                $paths[$relative] = $this->kitRoot($kit).'/'.$relative;
            }
        }

        return $this->copyMappedFiles($generator, $paths, $force);
    }

    /**
     * Core table UI (templates + optional controllers / icons under skeleton root).
     *
     * @return array{created: list<string>, updated: list<string>, skipped: list<string>}
     */
    public function installCore(Generator $generator, bool $withControllers, bool $withIcons, bool $force): array
    {
        $root = $this->skeletonRoot();
        $paths = [];

        foreach ($this->listRelativeFiles($root) as $relative) {
            if (str_starts_with($relative, 'kits/') || str_starts_with($relative, 'maker/')) {
                continue;
            }

            if (!$withControllers && str_starts_with($relative, 'assets/controllers/')) {
                continue;
            }

            if (!$withIcons && str_starts_with($relative, 'assets/icons/')) {
                continue;
            }

            if (
                str_starts_with($relative, 'templates/')
                || str_starts_with($relative, 'assets/controllers/')
                || str_starts_with($relative, 'assets/icons/')
            ) {
                $paths[$relative] = $root.'/'.$relative;
            }
        }

        return $this->copyMappedFiles($generator, $paths, $force);
    }

    /**
     * @param array<string, string> $paths target relative path => absolute source path
     *
     * @return array{created: list<string>, updated: list<string>, skipped: list<string>}
     */
    public function copyMappedFiles(Generator $generator, array $paths, bool $force): array
    {
        $filesystem = new Filesystem();
        $created = [];
        $updated = [];
        $skipped = [];

        ksort($paths);

        foreach ($paths as $target => $source) {
            if (!is_file($source)) {
                continue;
            }

            if (!$force && $filesystem->exists($generator->getRootDirectory().'/'.$target)) {
                $skipped[] = $target;
                continue;
            }

            $existed = $filesystem->exists($generator->getRootDirectory().'/'.$target);
            $generator->dumpFile($target, file_get_contents($source));

            if ($existed) {
                $updated[] = $target;
            } else {
                $created[] = $target;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param array{created: list<string>, updated: list<string>, skipped: list<string>} ...$results
     *
     * @return array{created: list<string>, updated: list<string>, skipped: list<string>}
     */
    public function mergeResults(array ...$results): array
    {
        $created = [];
        $updated = [];
        $skipped = [];

        foreach ($results as $result) {
            $created = [...$created, ...$result['created']];
            $updated = [...$updated, ...$result['updated']];
            $skipped = [...$skipped, ...$result['skipped']];
        }

        return [
            'created' => array_values(array_unique($created)),
            'updated' => array_values(array_unique($updated)),
            'skipped' => array_values(array_unique($skipped)),
        ];
    }

    /**
     * @param array{created: list<string>, updated: list<string>, skipped: list<string>} $result
     */
    public function writeResultSections(ConsoleStyle $io, array $result): void
    {
        if ($result['created']) {
            $io->section('Created');
            foreach ($result['created'] as $file) {
                $io->text('  • '.$file);
            }
        }

        if ($result['updated']) {
            $io->section('Updated');
            foreach ($result['updated'] as $file) {
                $io->text('  • '.$file);
            }
        }

        if ($result['skipped']) {
            $io->section('Skipped (already exists, use --force to overwrite)');
            foreach ($result['skipped'] as $file) {
                $io->text('  • '.$file);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function listRelativeFiles(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), \strlen($root))), '/');
            $files[] = $relative;
        }

        sort($files);

        return $files;
    }
}
