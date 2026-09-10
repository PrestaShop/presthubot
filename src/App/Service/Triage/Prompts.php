<?php

namespace Console\App\Service\Triage;

/**
 * Single source for the triage rubrics and their output schemas.
 *
 * The weekly run and the calibration must send byte-identical system prompts:
 * if they drift, the calibration measures something the agent does not actually
 * execute, and the prompt cache silently stops being shared between them.
 * Assembling them in one place is what guarantees that.
 */
class Prompts
{
    private const DIRECTORY = __DIR__ . '/../../Resources/triage';

    /**
     * The severity rubric, with the mined worked examples appended.
     *
     * Both halves are equally stable across a run, so both belong on the cached
     * side of the breakpoint.
     */
    public static function severity(): string
    {
        return self::read('severity_system.md')
            . PHP_EOL . PHP_EOL
            . self::read('severity_examples.md');
    }

    public static function pullRequest(): string
    {
        return self::read('pr_triage_system.md');
    }

    /**
     * @return array<string, mixed>
     */
    public static function schema(string $kind): array
    {
        $schemas = json_decode(self::read('schemas.json'), true);
        if (!is_array($schemas) || !isset($schemas[$kind]['schema'])) {
            throw new \RuntimeException('No output schema for: ' . $kind);
        }

        return $schemas[$kind]['schema'];
    }

    private static function read(string $file): string
    {
        $path = self::DIRECTORY . '/' . $file;
        if (!is_file($path)) {
            throw new \RuntimeException('Missing prompt resource: ' . $file);
        }

        return (string) file_get_contents($path);
    }
}
