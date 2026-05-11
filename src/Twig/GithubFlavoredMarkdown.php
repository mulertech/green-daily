<?php

declare(strict_types=1);

namespace App\Twig;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Twig\Extra\Markdown\MarkdownInterface;

class GithubFlavoredMarkdown implements MarkdownInterface
{
    private readonly GithubFlavoredMarkdownConverter $converter;

    public function __construct()
    {
        $this->converter = new GithubFlavoredMarkdownConverter();
    }

    public function convert(string $body): string
    {
        return $this->converter->convert($body)->getContent();
    }
}
