<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Markdown for CMS content (docs, pages, install steps). Raw HTML in the source is stripped
 * and unsafe links (javascript:, data:) are dropped, so admin-entered text cannot inject
 * scripts into public pages.
 */
class Markdown
{
    private static ?MarkdownConverter $converter = null;

    public static function toHtml(?string $source): string
    {
        if (blank($source)) {
            return '';
        }

        return (string) self::converter()->convert($source);
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $environment = new Environment([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new TableExtension);
            self::$converter = new MarkdownConverter($environment);
        }

        return self::$converter;
    }
}
