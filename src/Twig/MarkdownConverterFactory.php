<?php

declare(strict_types=1);

namespace App\Twig;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Twig\Extra\Markdown\LeagueMarkdown;
use Twig\Extra\Markdown\MarkdownInterface;

final class MarkdownConverterFactory
{
    public static function create(): MarkdownInterface
    {
        return new LeagueMarkdown(new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]));
    }
}
