<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Markdown for CMS content (docs, pages, install steps, known problems). Raw HTML in the source
 * is escaped and shown as text (so placeholders like <server-ip> stay visible), and unsafe links
 * (javascript:, data:) are dropped, so admin-entered text cannot inject scripts into public pages.
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
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
                // Every heading gets an id (#install-or-upgrade) so long docs can be linked into.
                'heading_permalink' => [
                    'apply_id_to_heading' => true,
                    'insert' => 'after',
                    'symbol' => '#',
                    'html_class' => 'heading-anchor',
                    'title' => 'Link to this section',
                    'id_prefix' => '',
                    'fragment_prefix' => '',
                    'min_heading_level' => 2,
                    'max_heading_level' => 3,
                ],
                // A line with only [TOC] becomes a list of the ## headings.
                'table_of_contents' => [
                    'position' => 'placeholder',
                    'placeholder' => '[TOC]',
                    'min_heading_level' => 2,
                    'max_heading_level' => 2,
                    'html_class' => 'doc-toc',
                ],
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new TableExtension);
            $environment->addExtension(new HeadingPermalinkExtension);
            $environment->addExtension(new TableOfContentsExtension);
            self::$converter = new MarkdownConverter($environment);
        }

        return self::$converter;
    }
}
