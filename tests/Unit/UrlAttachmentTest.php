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

it("creates an attachment with content type", function () {
    $attachment = UrlAttachment::fromUrl(
        "https://example.com/files/image.png",
        "image.png",
        "image/png",
    );

    expect($attachment)->toBeInstanceOf(Attachment::class);
});

it("returns null for non-url content", function () {
    $result = UrlAttachment::extractUrl("regular file content");

    expect($result)->toBeNull();
});

it("returns null for empty content", function () {
    $result = UrlAttachment::extractUrl("");

    expect($result)->toBeNull();
});

it("returns null for content with prefix but not in registry", function () {
    // Even if content starts with the marker prefix, it must be in the registry
    $result = UrlAttachment::extractUrl("FREESEND_URL_ATTACHMENT:fake123");

    expect($result)->toBeNull();
});

it("clears registry", function () {
    // Create an attachment to populate the registry
    UrlAttachment::fromUrl("https://example.com/file.pdf", "file.pdf");

    // Clear should work without error
    UrlAttachment::clearRegistry();

    // Any marker should now return null
    expect(
        UrlAttachment::extractUrl("FREESEND_URL_ATTACHMENT:anything"),
    )->toBeNull();
});
