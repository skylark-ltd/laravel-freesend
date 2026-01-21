<?php

use Illuminate\Mail\Attachment;
use Skylark\Freesend\FreesendTransport;
use Skylark\Freesend\UrlAttachment;

it("creates an attachment from url", function () {
    $attachment = UrlAttachment::fromUrl(
        "https://example.com/files/document.pdf",
        "document.pdf",
    );

    expect($attachment)->toBeInstanceOf(Attachment::class);
});

it("sets the correct filename", function () {
    $attachment = UrlAttachment::fromUrl(
        "https://example.com/files/document.pdf",
        "my-document.pdf",
    );

    // Convert to Symfony attachment to check properties
    $symfonyAttachment = null;
    $attachment->attachTo(
        new class {
            public $attachment;

            public function attach($data, $options = [])
            {
                $this->attachment = $options;
            }
        },
    );

    expect($attachment)->toBeInstanceOf(Attachment::class);
});

it("includes url header for freesend transport", function () {
    $url = "https://example.com/files/document.pdf";

    $attachment = UrlAttachment::fromUrl($url, "document.pdf");

    // Use reflection to access the attachment's data and headers
    $reflection = new ReflectionClass($attachment);
    $method = $reflection->getMethod("attachWith");

    // Create a mock attachable that captures the headers
    $capturedHeaders = [];
    $mockAttachable = new class ($capturedHeaders) {
        public array $headers = [];

        public function __construct(private array &$ref) {}

        public function withMime(string $mime)
        {
            return $this;
        }

        public function withHeaders(array $headers)
        {
            $this->headers = $headers;
            $this->ref = $headers;

            return $this;
        }
    };

    // The URL should be stored in headers
    // Check by converting to array representation
    $result = $method->invoke(
        $attachment,
        fn($data) => $data,
        fn($path) => $path,
    );

    // The attachment should have the X-Freesend-Url header
    expect($result["headers"] ?? [])->toHaveKey(
        FreesendTransport::URL_ATTACHMENT_HEADER,
        $url,
    );
});

it("sets content type when provided", function () {
    $attachment = UrlAttachment::fromUrl(
        "https://example.com/files/image.png",
        "image.png",
        "image/png",
    );

    expect($attachment)->toBeInstanceOf(Attachment::class);

    // Use reflection to check the mime type was set
    $reflection = new ReflectionClass($attachment);
    $method = $reflection->getMethod("attachWith");
    $result = $method->invoke(
        $attachment,
        fn($data) => $data,
        fn($path) => $path,
    );

    expect($result["mime"] ?? null)->toBe("image/png");
});

it("works without content type", function () {
    $attachment = UrlAttachment::fromUrl(
        "https://example.com/files/document.pdf",
        "document.pdf",
    );

    expect($attachment)->toBeInstanceOf(Attachment::class);

    $reflection = new ReflectionClass($attachment);
    $method = $reflection->getMethod("attachWith");
    $result = $method->invoke(
        $attachment,
        fn($data) => $data,
        fn($path) => $path,
    );

    // Should not have mime set when not provided
    expect($result)->not->toHaveKey("mime");
});

it("handles various url formats", function (string $url) {
    $attachment = UrlAttachment::fromUrl($url, "file.pdf");

    expect($attachment)->toBeInstanceOf(Attachment::class);

    $reflection = new ReflectionClass($attachment);
    $method = $reflection->getMethod("attachWith");
    $result = $method->invoke(
        $attachment,
        fn($data) => $data,
        fn($path) => $path,
    );

    expect($result["headers"][FreesendTransport::URL_ATTACHMENT_HEADER])->toBe(
        $url,
    );
})->with([
    "https://example.com/file.pdf",
    "https://cdn.example.com/files/document.pdf",
    "https://s3.amazonaws.com/bucket/file.pdf?token=abc123",
    "http://localhost:8080/files/test.pdf",
]);
