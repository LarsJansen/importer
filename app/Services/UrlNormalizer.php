<?php
namespace App\Services;

class UrlNormalizer
{
    /**
     * Conservative normalization for duplicate detection.
     * - lowercases scheme + host
     * - strips fragments
     * - strips default ports
     * - collapses duplicate slashes in path
     * - trims trailing slash except for root path
     * - preserves query string
     * - preserves scheme and preserves www
     */
    public static function normalize(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $parts = @parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
        $host = strtolower((string) $parts['host']);
        $port = $parts['port'] ?? null;
        $path = (string) ($parts['path'] ?? '/');
        $query = (string) ($parts['query'] ?? '');

        $path = preg_replace('#/+#', '/', $path) ?: '/';
        if ($path !== '/') {
            $path = rtrim($path, '/');
            if ($path === '') {
                $path = '/';
            }
        }

        $isDefaultPort = ($scheme === 'http' && (int) $port === 80)
            || ($scheme === 'https' && (int) $port === 443);

        $normalized = $scheme . '://' . $host;

        if ($port !== null && !$isDefaultPort) {
            $normalized .= ':' . (int) $port;
        }

        $normalized .= $path;

        if ($query !== '') {
            $normalized .= '?' . $query;
        }

        return $normalized;
    }
}
