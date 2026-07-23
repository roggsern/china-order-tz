<?php

namespace App\Support\Security;

/**
 * RC1-G4B — DOM allowlist HTML sanitizer (not blacklist-regex).
 *
 * Allowed tags: p, br, strong, b, em, i, u, ul, ol, li, h2–h4, a, span, blockquote.
 * Allowed attributes: href on <a> only (http/https/# / relative paths; never javascript/data).
 */
final class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
        'h2', 'h3', 'h4', 'a', 'span', 'blockquote',
    ];

    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $trimmed = trim($html);
        if ($trimmed === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<!DOCTYPE html><html><body><div id="html-sanitizer-root">'.$trimmed.'</div></body></html>',
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('html-sanitizer-root');
        if (! $root instanceof \DOMElement) {
            $xpath = new \DOMXPath($dom);
            $found = $xpath->query('//*[@id="html-sanitizer-root"]')->item(0);
            $root = $found instanceof \DOMElement ? $found : null;
        }
        if (! $root instanceof \DOMElement) {
            return htmlspecialchars($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        foreach (iterator_to_array($root->childNodes) as $child) {
            self::sanitizeNode($child);
        }

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return;
        }

        if (! $node instanceof \DOMElement) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, [
            'script', 'style', 'iframe', 'object', 'embed', 'svg', 'form',
            'link', 'meta', 'base', 'noscript', 'applet', 'frame', 'frameset',
        ], true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            self::sanitizeNode($child);
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            $parent = $node->parentNode;
            if ($parent) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
            }

            return;
        }

        $attrs = [];
        if ($node->hasAttributes()) {
            foreach (iterator_to_array($node->attributes) as $attr) {
                $attrs[strtolower($attr->name)] = $attr->value;
                $node->removeAttributeNode($attr);
            }
        }

        if ($tag === 'a' && isset($attrs['href']) && self::isSafeHref((string) $attrs['href'])) {
            $node->setAttribute('href', trim(html_entity_decode((string) $attrs['href'], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
    }

    private static function isSafeHref(string $href): bool
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $href = preg_replace('/[\x00-\x1F\x7F]+/u', '', $href) ?? $href;
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#')) {
            return true;
        }

        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return ! str_contains($href, '..');
        }

        $compact = strtolower(preg_replace('/\s+/', '', $href) ?? $href);
        if (
            str_starts_with($compact, 'javascript:')
            || str_starts_with($compact, 'data:')
            || str_starts_with($compact, 'vbscript:')
        ) {
            return false;
        }

        return (bool) preg_match('#^https?://#i', $href);
    }
}
