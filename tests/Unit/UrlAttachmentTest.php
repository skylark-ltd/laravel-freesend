<?php

use Illuminate\Mail\Attachment;
use Skylark\Freesend\UrlAttachment;

beforeEach(function () {
    UrlAttachment::clearRegistry();
});

it("creates an attachment from url", function () {
    $attachment = UrlAttachment::fromUrl(
        "https://example.com/files/document.pdf",
        "document.pdf",
    );

    expect($attachment)->toBeInstanceOf(Attachment::class);
});

it("stores url in registry and extracts it", function () {
    $url = "https://example.com/files/document.pdf";

    $attachment = UrlAttachment::fromUrl($url, "document.pdf");

    // Extract the marker from the attachment
    $marker = null;
    $attachment->attachTo(
        new class ($marker) {
            public function __construct(private &$marker) {}
            public function attach($data, $options = [])
            {
                $this->marker = is_callable($data) ? $data() : $data;
            }
        },
    );

    // The marker should be extractable to the original URL
    $extractedUrl = UrlAttachment::extractUrl($marker);

    expect($extractedUrl)->toBe($url);
});

it("returns null for non-url content", function () {
    $result = UrlAttachment::extractUrl("regular file content");

    expect($result)->toBeNull();
});

it("returns null for empty content", function () {
    $result = UrlAttachment::extractUrl("");

    expect($result)->toBeNull();
});

it("sets content type when provided", function () {
    $attachment = UrlAttachment::fromUrl(
        "https://example.com/files/image.png",
        "image.png",
        "image/png",
    );

    expect($attachment)->toBeInstanceOf(Attachment::class);
});

it("works without content type", function () {
    $attachment = UrlAttachment::fromUrl(
        "https://example.com/files/document.pdf",
        "document.pdf",
    );

    expect($attachment)->toBeInstanceOf(Attachment::class);
});

it("handles various url formats", function (string $url) {
    $attachment = UrlAttachment::fromUrl($url, "file.pdf");

    expect($attachment)->toBeInstanceOf(Attachment::class);

    // Extract and verify
    $marker = null;
    $attachment->attachTo(
        new class ($marker) {
            public function __construct(private &$marker) {}
            public function attach($data, $options = [])
            {
                $this->marker = is_callable($data) ? $data() : $data;
            }
        },
    );

    $extractedUrl = UrlAttachment::extractUrl($marker);

    expect($extractedUrl)->toBe($url);
})->with([
    "https://example.com/file.pdf",
    "https://cdn.example.com/files/document.pdf",
    "https://s3.amazonaws.com/bucket/file.pdf?token=abc123",
    "http://localhost:8080/files/test.pdf",
]);

it("generates unique markers for same url", function () {
    $url = "https://example.com/files/document.pdf";

    $attachment1 = UrlAttachment::fromUrl($url, "doc1.pdf");
    $attachment2 = UrlAttachment::fromUrl($url, "doc2.pdf");

    $marker1 = null;
    $marker2 = null;

    $attachment1->attachTo(
        new class ($marker1) {
            public function __construct(private &$marker) {}
            public function attach($data, $options = [])
            {
                $this->marker = is_callable($data) ? $data() : $data;
            }
        },
    );

    $attachment2->attachTo(
        new class ($marker2) {
            public function __construct(private &$marker) {}
            public function attach($data, $options = [])
            {
                $this->marker = is_callable($data) ? $data() : $data;
            }
        },
    );

    // Markers should be different even for same URL
    expect($marker1)->not->toBe($marker2);

    // But both should resolve to the same URL
    expect(UrlAttachment::extractUrl($marker1))->toBe($url);
    expect(UrlAttachment::extractUrl($marker2))->toBe($url);
});

it("clears registry", function () {
    $url = "https://example.com/files/document.pdf";
    $attachment = UrlAttachment::fromUrl($url, "document.pdf");

    $marker = null;
    $attachment->attachTo(
        new class ($marker) {
            public function __construct(private &$marker) {}
            public function attach($data, $options = [])
            {
                $this->marker = is_callable($data) ? $data() : $data;
            }
        },
    );

    // Should be extractable before clearing
    expect(UrlAttachment::extractUrl($marker))->toBe($url);

    // Clear the registry
    UrlAttachment::clearRegistry();

    // Should no longer be extractable
    expect(UrlAttachment::extractUrl($marker))->toBeNull();
});
