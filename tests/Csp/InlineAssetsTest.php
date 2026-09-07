<?php

declare(strict_types=1);

namespace App\Tests\Csp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The policy names a nonce for `script-src` and `style-src`, and a nonce makes the browser
 * ignore `'unsafe-inline'`: a `<script>` or `<style>` block served without one is dropped, an
 * inline event handler never runs, and a `style` attribute carries no nonce at all.
 *
 * None of those failures reaches the server. The response is a 200, the page is complete, and
 * the browser refuses a piece of it in silence. This is where they get caught instead.
 */
final class InlineAssetsTest extends TestCase
{
    /**
     * Templates rendered into an email, which no browser loads and no policy governs. A new
     * one failing this test is the point: it has to be declared here, not assumed.
     *
     * @var list<string>
     */
    private const array NOT_SERVED_TO_A_BROWSER = [];

    /**
     * @return iterable<string, array{string}>
     */
    public static function templateProvider(): iterable
    {
        $root = \dirname(__DIR__, 2).'/templates';
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }

            $relative = substr($file->getPathname(), \strlen($root) + 1);

            if (!\in_array($relative, self::NOT_SERVED_TO_A_BROWSER, true)) {
                yield $relative => [$file->getPathname()];
            }
        }
    }

    /**
     * A `style` attribute can carry neither a nonce nor a usable hash: it is rewritten as a
     * class, or as a rule inside a nonced `<style>` block with a selector by `id`.
     */
    #[DataProvider('templateProvider')]
    public function testTemplateServesNoStyleAttribute(string $path): void
    {
        self::assertOffenders($path, '/(?<![\w-])style\s*=\s*["\']/i', 'a style attribute');
    }

    /**
     * An inline handler needs `'unsafe-inline'` in `script-src`, which the nonce cancels: it
     * moves to a module served from `'self'`, reached through a `data-` attribute.
     */
    #[DataProvider('templateProvider')]
    public function testTemplateServesNoInlineEventHandler(string $path): void
    {
        self::assertOffenders($path, '/(?<![\w-])on[a-z]+\s*=\s*["\']/i', 'an inline event handler');
        self::assertOffenders($path, '/["\']javascript:/i', 'a javascript: URL');
    }

    #[DataProvider('templateProvider')]
    public function testTemplateCarriesTheNonceOnEveryInlineBlock(string $path): void
    {
        $contents = (string) file_get_contents($path);
        $offenders = [];

        preg_match_all('/<(style|script)\\b[^>]*>/i', $contents, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $index => [$tag, $offset]) {
            // A script pulling a file is governed by its source, not by a nonce.
            if ('script' === strtolower($matches[1][$index][0]) && 1 === preg_match('/\\ssrc\\s*=/i', $tag)) {
                continue;
            }

            if (!str_contains($tag, 'csp_nonce')) {
                $offenders[] = sprintf('  %d: %s', 1 + substr_count(substr($contents, 0, $offset), "\n"), $tag);
            }
        }

        self::assertSame(
            [],
            $offenders,
            sprintf("%s serves an inline block without the CSP nonce:\n%s", $path, implode("\n", $offenders)),
        );
    }

    private static function assertOffenders(string $path, string $pattern, string $what): void
    {
        $offenders = [];

        foreach (explode("\n", (string) file_get_contents($path)) as $index => $line) {
            if (1 === preg_match($pattern, $line)) {
                $offenders[] = sprintf('  %d: %s', $index + 1, trim($line));
            }
        }

        self::assertSame(
            [],
            $offenders,
            sprintf("%s serves %s, which the policy blocks:\n%s", $path, $what, implode("\n", $offenders)),
        );
    }
}
