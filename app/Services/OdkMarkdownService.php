<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Converts between the ODK-markdown subset stored in language_strings.text
 * (**bold**, _italic_, literal newlines — the only formatting ODK Collect
 * renders in hints) and the HTML used by the Filament RichEditor.
 *
 * The mapping is deliberately lossless in both directions so that filling an
 * editor and saving without edits leaves the stored text byte-identical.
 */
class OdkMarkdownService
{
    public function toHtml(?string $odkMarkdown): string
    {
        if (blank($odkMarkdown)) {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $odkMarkdown);
        $text = e($text);
        $text = preg_replace('/\*\*([^*\n]+)\*\*/u', '<strong>$1</strong>', $text);
        $text = preg_replace('/(?<![\w_])_([^_\n]+)_(?![\w_])/u', '<em>$1</em>', $text);

        return '<p>'.str_replace("\n", '<br>', $text).'</p>';
    }

    public function fromHtml(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $document = new DOMDocument;
        $previousErrorSetting = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8"?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_use_internal_errors($previousErrorSetting);

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        $markdown = $this->convertChildren($body);
        $markdown = str_replace("\u{00A0}", ' ', $markdown);
        $markdown = preg_replace('/[ \t]+\n/', "\n", $markdown);

        return trim($markdown, "\n ");
    }

    protected function convertChildren(DOMNode $node): string
    {
        $output = '';

        foreach ($node->childNodes as $childNode) {
            $output .= $this->convertNode($childNode);
        }

        return $output;
    }

    protected function convertNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return $node->textContent;
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $children = $this->convertChildren($node);

        return match (strtolower($node->tagName)) {
            'strong', 'b' => $children === '' ? '' : "**{$children}**",
            'em', 'i' => $children === '' ? '' : "_{$children}_",
            'br' => "\n",
            'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote' => rtrim($children, "\n")."\n\n",
            'li' => rtrim($children, "\n")."\n",
            default => $children,
        };
    }
}
