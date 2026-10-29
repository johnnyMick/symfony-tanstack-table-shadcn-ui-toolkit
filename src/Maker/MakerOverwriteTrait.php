<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Maker;

use Symfony\Bundle\MakerBundle\Generator;

/**
 * Helpers so --force actually overwrites: MakerBundle's Generator refuses existing files.
 */
trait MakerOverwriteTrait
{
    private function projectRoot(): string
    {
        $dir = getcwd() ?: '.';
        while (true) {
            if (is_file($dir.'/composer.json') && (is_dir($dir.'/templates') || is_dir($dir.'/src'))) {
                return $dir;
            }
            $parent = \dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return getcwd() ?: '.';
    }

    private function classRelativePath(Generator $generator, string $fqcn): string
    {
        return 'src/'.str_replace('\\', '/', substr(
            $fqcn,
            \strlen($generator->getRootNamespace()) + 1
        )).'.php';
    }

    /**
     * Throw if the path exists and --force was not passed; otherwise delete so Generator can write.
     */
    private function prepareOverwrite(Generator $generator, string $relativePath, bool $force): void
    {
        $absolute = $generator->getRootDirectory().'/'.$relativePath;
        if (!is_file($absolute)) {
            return;
        }

        if (!$force) {
            throw new \RuntimeException(\sprintf('"%s" already exists. Use --force to overwrite.', $relativePath));
        }

        if (!@unlink($absolute)) {
            throw new \RuntimeException(\sprintf('Could not remove existing file "%s" for --force overwrite.', $relativePath));
        }
    }
}
