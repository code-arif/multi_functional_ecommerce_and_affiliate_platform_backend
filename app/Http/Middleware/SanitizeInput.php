<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * SanitizeInput Middleware
 *
 * Recursively strips dangerous HTML/script content from all
 * incoming request inputs before they reach controllers.
 *
 * Skipped fields:
 *   - password / password_confirmation (must not be modified)
 *   - description / content           (CMS fields allow rich HTML — validated separately)
 */
class SanitizeInput
{
    /**
     * Input keys that should NOT be sanitized (raw content allowed).
     */
    private array $except = [
        'password',
        'password_confirmation',
        'current_password',
    ];

    /**
     * Input keys that allow limited HTML (CMS / product description).
     */
    private array $allowHtml = [
        'description',
        'content',
        'body',
        'short_description',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Sanitize query params
        $this->clean($request->query);

        // Sanitize request body
        $this->clean($request->request);

        return $next($request);
    }

    private function clean(\Symfony\Component\HttpFoundation\InputBag $bag): void
    {
        $cleaned = $this->sanitizeArray($bag->all());
        $bag->replace($cleaned);
    }

    private function sanitizeArray(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            // Skip protected fields
            if (in_array($key, $this->except)) {
                $result[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->sanitizeArray($value, $fullKey);
            } elseif (is_string($value)) {
                // Fields that allow limited HTML get strip_tags with allowed tags
                if (in_array($key, $this->allowHtml)) {
                    $result[$key] = $this->sanitizeHtml($value);
                } else {
                    $result[$key] = $this->sanitizeString($value);
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Strip all HTML and encode entities — for plain text fields.
     */
    private function sanitizeString(string $value): string
    {
        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Strip all HTML tags
        $value = strip_tags($value);

        // Convert special chars to HTML entities to prevent XSS
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);

        // Decode once so legitimate chars (é, ñ, etc.) are preserved
        $value = htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5);

        return trim($value);
    }

    /**
     * Strip dangerous tags, event handlers, and unsafe URI schemes for CMS/rich-text fields
     * using DOM parsing rather than regex-only attribute stripping.
     */
    private function sanitizeHtml(string $value): string
    {
        $value = str_replace("\0", '', $value);

        $allowedTags = '<p><br><b><i><u><strong><em><ul><ol><li><h1><h2><h3><h4><a><img><blockquote><code><pre><table><thead><tbody><tr><td><th><span><div>';
        $stripped = strip_tags($value, $allowedTags);

        if (trim($stripped) === '') {
            return '';
        }

        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="utf-8" ?><div>' . $stripped . '</div>';

        if (!$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            return strip_tags($stripped);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $blockedTags = ['script', 'iframe', 'object', 'embed', 'form', 'style', 'svg', 'meta', 'link', 'base'];
        foreach ($blockedTags as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                $node?->parentNode?->removeChild($node);
            }
        }

        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $toRemove = [];
            foreach ($element->attributes as $attr) {
                $attrName = strtolower($attr->name);
                $attrVal  = trim($attr->value);

                // Strip all inline event handlers (on*) and style attributes
                if (str_starts_with($attrName, 'on') || $attrName === 'style') {
                    $toRemove[] = $attr->name;
                    continue;
                }

                // Enforce safe URL schemes on link/media attributes
                if (in_array($attrName, ['href', 'src', 'xlink:href', 'formaction'], true)) {
                    $normalized = preg_replace('/[\x00-\x20]+/', '', strtolower($attrVal));
                    $isSafe = str_starts_with($normalized, 'http://')
                        || str_starts_with($normalized, 'https://')
                        || str_starts_with($normalized, 'mailto:')
                        || str_starts_with($normalized, '/')
                        || str_starts_with($normalized, '#');
                    if (!$isSafe) {
                        $toRemove[] = $attr->name;
                    }
                }
            }
            foreach ($toRemove as $name) {
                $element->removeAttribute($name);
            }
        }

        $container = $dom->getElementsByTagName('div')->item(0);
        if (!$container) {
            return strip_tags($stripped);
        }

        $clean = '';
        foreach ($container->childNodes as $child) {
            $clean .= $dom->saveHTML($child);
        }

        return $clean;
    }
}