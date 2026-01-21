<?php

namespace Skylarkltd\Freesend;

use Illuminate\Mail\Attachment;

/**
 * Helper class for creating URL-based email attachments.
 *
 * The Freesend API supports two types of attachments:
 * 1. Base64-encoded content (standard Laravel attachments)
 * 2. URL-based attachments where Freesend fetches the file from a URL
 *
 * This class provides a convenient way to create URL-based attachments
 * that work with Laravel's standard Mailable attachment system.
 *
 * URL-based attachments are useful when:
 * - The file is already hosted online (e.g., in cloud storage)
 * - You want to avoid base64 encoding large files
 * - The file is dynamically generated via a URL
 *
 * Note: Freesend only supports HTTP/HTTPS URLs and has a 30-second
 * timeout for fetching URL-based attachments. Maximum file size is 25MB.
 *
 * @example
 * ```php
 * use Skylark\Freesend\UrlAttachment;
 *
 * // In a Mailable class:
 * public function attachments(): array
 * {
 *     return [
 *         UrlAttachment::fromUrl(
 *             'https://example.com/files/report.pdf',
 *             'monthly-report.pdf',
 *             'application/pdf'
 *         ),
 *     ];
 * }
 * ```
 *
 * @see https://freesend.metafog.io/docs/api/send-email
 */
class UrlAttachment
{
    /**
     * Prefix used to identify URL attachment markers in content.
     */
    private const MARKER_PREFIX = "FREESEND_URL_ATTACHMENT:";

    /**
     * Registry mapping markers to URLs.
     *
     * @var array<string, string>
     */
    private static array $urlRegistry = [];

    /**
     * Create a URL-based attachment for use with the Freesend transport.
     *
     * This method creates a Laravel Attachment with a special marker in its
     * content. When the FreesendTransport processes this attachment, it will
     * recognize the marker and send the URL to the Freesend API instead of
     * base64-encoding the content.
     *
     * @param string $url The HTTP/HTTPS URL where the file can be fetched.
     * @param string $filename The filename to use for the attachment in the email.
     * @param string|null $contentType Optional MIME type (e.g., 'application/pdf').
     *                                  If not provided, Freesend will attempt to detect it.
     *
     * @return Attachment A Laravel Attachment configured for URL-based delivery.
     *
     * @example
     * ```php
     * // Basic usage
     * UrlAttachment::fromUrl('https://example.com/file.pdf', 'document.pdf');
     *
     * // With explicit content type
     * UrlAttachment::fromUrl(
     *     'https://cdn.example.com/images/logo.png',
     *     'company-logo.png',
     *     'image/png'
     * );
     *
     * // From cloud storage
     * UrlAttachment::fromUrl(
     *     Storage::temporaryUrl('reports/annual.pdf', now()->addMinutes(30)),
     *     'annual-report.pdf',
     *     'application/pdf'
     * );
     * ```
     */
    public static function fromUrl(
        string $url,
        string $filename,
        ?string $contentType = null,
    ): Attachment {
        // Create a unique marker for this URL
        $marker = self::MARKER_PREFIX . md5($url . $filename . microtime());

        // Store the URL in the registry
        self::$urlRegistry[$marker] = $url;

        // Create attachment with the marker as content
        $attachment = Attachment::fromData(fn() => $marker, $filename);

        if ($contentType !== null) {
            $attachment = $attachment->withMime($contentType);
        }

        return $attachment;
    }

    /**
     * Extract a URL from attachment content if it's a URL marker.
     *
     * @param string $content The attachment content to check.
     *
     * @return string|null The URL if found, null otherwise.
     *
     * @internal Used by FreesendTransport to detect URL attachments.
     */
    public static function extractUrl(string $content): ?string
    {
        if (
            str_starts_with($content, self::MARKER_PREFIX) &&
            isset(self::$urlRegistry[$content])
        ) {
            return self::$urlRegistry[$content];
        }

        return null;
    }

    /**
     * Clear the URL registry.
     *
     * @return void
     *
     * @internal Used for testing purposes.
     */
    public static function clearRegistry(): void
    {
        self::$urlRegistry = [];
    }
}
