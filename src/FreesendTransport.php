<?php

namespace Skylark\Freesend;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Symfony Mailer transport for the Freesend email API.
 *
 * This transport sends emails through the Freesend API service,
 * supporting HTML/text content and file attachments (both base64 and URL-based).
 *
 * @see https://freesend.metafog.io/docs/api/send-email
 */
class FreesendTransport extends AbstractTransport
{
    /**
     * Custom header name used to mark attachments for URL-based delivery.
     *
     * When this header is present on an attachment, the header value
     * will be used as the attachment URL instead of base64-encoding the content.
     */
    public const URL_ATTACHMENT_HEADER = "X-Freesend-Url";

    /**
     * Create a new Freesend transport instance.
     *
     * @param string $apiKey The Freesend API key for authentication.
     * @param string $endpoint The Freesend API endpoint URL.
     * @param Client|null $client Optional Guzzle HTTP client instance.
     * @param EventDispatcherInterface|null $dispatcher Optional event dispatcher.
     * @param LoggerInterface|null $logger Optional logger instance.
     */
    public function __construct(
        protected string $apiKey,
        protected string $endpoint,
        protected ?Client $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);

        $this->client =
            $client ??
            new Client([
                "timeout" => 30,
                "connect_timeout" => 10,
            ]);
    }

    /**
     * Send the email message via the Freesend API.
     *
     * @param SentMessage $message The message to send.
     *
     * @throws TransportException If the API request fails or returns an error.
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $payload = $this->buildPayload($email, $envelope);

        try {
            $response = $this->client->post($this->endpoint, [
                "headers" => [
                    "Authorization" => "Bearer " . $this->apiKey,
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                ],
                "json" => $payload,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $body = (string) $response->getBody();
                throw new TransportException(
                    sprintf(
                        "Freesend API returned status %d: %s",
                        $statusCode,
                        $body,
                    ),
                );
            }
        } catch (GuzzleException $e) {
            throw new TransportException(
                sprintf(
                    "Failed to send email via Freesend: %s",
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }
    }

    /**
     * Build the API request payload from the email message.
     *
     * @param Email $email The email message.
     * @param Envelope $envelope The message envelope containing sender/recipient info.
     *
     * @return array<string, mixed> The payload array for the Freesend API.
     */
    protected function buildPayload(Email $email, Envelope $envelope): array
    {
        $from = $email->getFrom()[0] ?? $envelope->getSender();

        $payload = [
            "fromEmail" => $from->getAddress(),
            "to" => $this->getRecipient($email, $envelope),
            "subject" => $email->getSubject() ?? "",
        ];

        if ($fromName = $from->getName()) {
            $payload["fromName"] = $fromName;
        }

        if ($html = $email->getHtmlBody()) {
            $payload["html"] = $html;
        }

        if ($text = $email->getTextBody()) {
            $payload["text"] = $text;
        }

        if (!isset($payload["html"]) && !isset($payload["text"])) {
            $payload["text"] = "";
        }

        $attachments = $this->buildAttachments($email);
        if (!empty($attachments)) {
            $payload["attachments"] = $attachments;
        }

        return $payload;
    }

    /**
     * Extract the primary recipient email address from the message.
     *
     * @param Email $email The email message.
     * @param Envelope $envelope The message envelope.
     *
     * @return string The recipient email address.
     *
     * @throws TransportException If no recipient address is found.
     */
    protected function getRecipient(Email $email, Envelope $envelope): string
    {
        $to = $email->getTo();

        if (!empty($to)) {
            return $to[0]->getAddress();
        }

        $recipients = $envelope->getRecipients();

        if (!empty($recipients)) {
            return $recipients[0]->getAddress();
        }

        throw new TransportException("No recipient address provided");
    }

    /**
     * Build the attachments array for the API payload.
     *
     * Supports both base64-encoded content attachments and URL-based attachments.
     * URL attachments are identified by the presence of the X-Freesend-Url header.
     *
     * @param Email $email The email message containing attachments.
     *
     * @return array<int, array<string, string>> Array of attachment data for the API.
     *
     * @see UrlAttachment For creating URL-based attachments.
     */
    protected function buildAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $attachmentData = [
                "filename" => $attachment->getFilename() ?? "attachment",
            ];

            $url = $this->getUrlFromAttachment($attachment);

            if ($url !== null) {
                $attachmentData["url"] = $url;
            } else {
                $attachmentData["content"] = base64_encode(
                    $attachment->getBody(),
                );
            }

            $contentType = $attachment->getContentType();
            if ($contentType) {
                $attachmentData["contentType"] = $contentType;
            }

            $attachments[] = $attachmentData;
        }

        return $attachments;
    }

    /**
     * Extract the URL from a URL-based attachment, if present.
     *
     * Checks for the X-Freesend-Url header on the attachment part.
     *
     * @param DataPart $attachment The attachment to check.
     *
     * @return string|null The attachment URL, or null if not a URL attachment.
     */
    protected function getUrlFromAttachment(DataPart $attachment): ?string
    {
        $headers = $attachment->getPreparedHeaders();

        if ($headers->has(self::URL_ATTACHMENT_HEADER)) {
            return $headers->get(self::URL_ATTACHMENT_HEADER)->getBody();
        }

        return null;
    }

    /**
     * Get the string representation of the transport.
     *
     * @return string The transport name.
     */
    public function __toString(): string
    {
        return "freesend";
    }
}
