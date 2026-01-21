<?php

namespace Skylark\Freesend;

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
     * Create a URL-based attachment for use with the Freesend transport.
     *
     * This method creates a Laravel Attachment that is marked with a special
     * header. When the FreesendTransport processes this attachment, it will
     * send the URL to the Freesend API instead of base64-encoding the content.
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
        $attachment = Attachment::fromData(fn() => "", $filename)->withHeaders([
            FreesendTransport::URL_ATTACHMENT_HEADER => $url,
        ]);

        if ($contentType !== null) {
            $attachment = $attachment->withMime($contentType);
        }

        return $attachment;
    }
}
