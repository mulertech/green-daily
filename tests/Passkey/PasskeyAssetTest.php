<?php

declare(strict_types=1);

namespace App\Tests\Passkey;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The passkey markup is inert on its own: the buttons carry their configuration as data
 * attributes, and the bundle asset is what binds them. A page shipping the markup without the
 * module answers a plain 200, shows the button, and does nothing at all when it is clicked,
 * without an error anywhere, in the browser console no more than in the server log.
 *
 * The two therefore travel together, and this is what says so. A template satisfies the rule by
 * importing the module itself, by extending a layout that does, or, when it is a partial, by
 * being included only from templates that do.
 *
 * @author Sébastien Muler
 */
final class PasskeyAssetTest extends TestCase
{
    private const string MODULE = 'bundles/mulertechpasskey/passkey.js';

    /**
     * The management page shipped by the bundle carries its own "add a passkey" button, from a
     * template that lives in `vendor/` and that no reading of `templates/` sees. Overriding its
     * layout is what places the module on that page.
     */
    private const string BUNDLE_PAGE_LAYOUT = 'bundles/MulerTechPasskeyBundle/passkey/layout.html.twig';

    /**
     * @return iterable<string, array{string}>
     */
    public static function passkeyTemplateProvider(): iterable
    {
        $root = self::templateRoot();
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (str_contains($contents, 'data-passkey-action')) {
                yield substr($file->getPathname(), \strlen($root) + 1) => [
                    substr($file->getPathname(), \strlen($root) + 1),
                ];
            }
        }
    }

    #[DataProvider('passkeyTemplateProvider')]
    public function testPasskeyMarkupTravelsWithItsModule(string $relative): void
    {
        self::assertTrue(
            self::servesTheModule($relative),
            sprintf(
                '%s serves passkey buttons, but neither it nor its layout imports %s: the buttons '
                ."are bound to nothing and clicking them does nothing at all.\n"
                .'Add to the template, or to the layout it extends: '
                .'<script type="module" nonce="{{ csp_nonce(\'main\') }}">'
                ."import '{{ asset('%s') }}';</script>",
                $relative,
                self::MODULE,
                self::MODULE,
            ),
        );
    }

    /**
     * The routes and the layout override answer for each other. Importing the routes without the
     * override serves a page whose button is bound to nothing; keeping the override without the
     * routes leaves a template nothing renders, which the next reader will take for a live page.
     */
    public function testTheBundlePageAndItsLayoutOverrideGoTogether(): void
    {
        $override = self::templateRoot().'/'.self::BUNDLE_PAGE_LAYOUT;

        if (!file_exists(self::projectRoot().'/config/routes/mulertech_passkey.yaml')) {
            self::assertFileDoesNotExist(
                $override,
                sprintf(
                    '%s overrides the layout of a page this application does not route: either '
                    .'import @MulerTechPasskeyBundle/config/routes.yaml, or delete the override.',
                    self::BUNDLE_PAGE_LAYOUT,
                ),
            );

            return;
        }

        self::assertFileExists(
            $override,
            sprintf(
                'The bundle management page is routed but its layout is not overridden, so nothing '
                .'imports %s on that page and the "add a passkey" button does nothing.',
                self::MODULE,
            ),
        );

        self::assertTrue(
            self::servesTheModule(self::BUNDLE_PAGE_LAYOUT),
            sprintf('%s does not lead to %s.', self::BUNDLE_PAGE_LAYOUT, self::MODULE),
        );
    }

    /**
     * @param list<string> $seen guards against a cycle in the template graph
     */
    private static function servesTheModule(string $relative, array $seen = []): bool
    {
        if (\in_array($relative, $seen, true)) {
            return false;
        }

        $seen[] = $relative;
        $path = self::templateRoot().'/'.$relative;

        if (!is_file($path)) {
            return false;
        }

        $contents = (string) file_get_contents($path);

        if (str_contains($contents, self::MODULE)) {
            return true;
        }

        if (1 === preg_match('/\{%\s*extends\s+[\'"]([^\'"]+)[\'"]/', $contents, $matches)) {
            return self::servesTheModule($matches[1], $seen);
        }

        // A partial has no layout of its own: it is covered by whoever includes it, all of them.
        $includers = self::includersOf($relative);

        if ([] === $includers) {
            return false;
        }

        foreach ($includers as $includer) {
            if (!self::servesTheModule($includer, $seen)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function includersOf(string $relative): array
    {
        $root = self::templateRoot();
        $includers = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }

            $candidate = substr($file->getPathname(), \strlen($root) + 1);

            if ($candidate === $relative) {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), $relative)) {
                $includers[] = $candidate;
            }
        }

        return $includers;
    }

    private static function templateRoot(): string
    {
        return self::projectRoot().'/templates';
    }

    private static function projectRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}
