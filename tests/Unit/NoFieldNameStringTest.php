<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Indicateur de la 1.0 : 100 % des requêtes de la démo écrites sans chaîne de nom de champ. Les
 * requêtes passent par le QueryBuilder typé (Order_::as('o')) ; findBy() et ses critères en
 * tableau ne sont jamais utilisés.
 */
final class NoFieldNameStringTest extends TestCase
{
    public function testQueriesNeverNameAFieldWithAString(): void
    {
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__, 2) . '/src', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $code = (string) file_get_contents($file->getPathname());
            if (1 === preg_match('/->(findBy|findOneBy|count|iterate)\(\s*\[|->getField\(\s*\'|\bcreateQueryBuilder\(/', $code, $m)) {
                $offenders[] = $file->getFilename() . ': ' . $m[0];
            }
        }

        self::assertSame([], $offenders);
    }
}
