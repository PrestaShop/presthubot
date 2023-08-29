<?php

namespace Console\App\Service;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileSystem
{
    /**
     * @return array<int, array{file: string, num: int, line: string}>
     */
    public static function search(string $directory, string $search): array
    {
        $results = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $iterator->rewind();
        while ($iterator->valid()) {
            if ($iterator->isFile() && $iterator->getExtension() === 'ts') {
                $lines = file($iterator->getPathname());

                foreach ($lines as $key => $line) {
                    if (strpos($line, $search) !== false) {
                        $results[] = [
                            'file' => $iterator->getPathname(),
                            'num' => $key + 1,
                            'line' => $line,
                        ];
                    }
                }
            }

            $iterator->next();
        }

        return $results;
    }
}
