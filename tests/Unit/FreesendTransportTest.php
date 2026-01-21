<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Skylark\Freesend\FreesendTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    $this->apiKey = "test-api-key";
    $this->endpoint = "https://freesend.test/api/send-email";
});

it("can be instantiated with required parameters", function () {
    $transport = new FreesendTransport($this->apiKey, $this->endpoint);

    expect((string) $transport)->toBe("freesend");
});

it("sends a basic email successfully", function () {
    $mock = new MockHandler([
        new Response(
            200,
            [],
            json_encode(["message" => "Email sent successfully"]),
        ),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(["handler" => $handlerStack]);

    $transport = new FreesendTransport($this->apiKey, $this->endpoint, $client);

    $email = new Email()
        ->from("sender@example.com")
        ->to("recipient@example.com")
        ->subject("Test Subject")
        ->text("Test body content");

    $sentMessage = $transport->send($email);

    expect($sentMessage)->not->toBeNull();
});

it("sends an email with html content", function () {
    $capturedRequest = null;

    $mock = new MockHandler([
        function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;

            return new Response(
                200,
                [],
                json_encode(["message" => "Email sent successfully"]),
            );
        },
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(["handler" => $handlerStack]);

    $transport = new FreesendTransport($this->apiKey, $this->endpoint, $client);

    $email = new Email()
        ->from("sender@example.com")
        ->to("recipient@example.com")
        ->subject("Test Subject")
        ->html("<h1>Hello World</h1>");

    $transport->send($email);

    $body = json_decode($capturedRequest->getBody()->getContents(), true);

    expect($body)
        ->toHaveKey("html", "<h1>Hello World</h1>")
        ->toHaveKey("fromEmail", "sender@example.com")
        ->toHaveKey("to", "recipient@example.com")
        ->toHaveKey("subject", "Test Subject");
});

it("includes from name when provided", function () {
    $capturedRequest = null;

    $mock = new MockHandler([
        function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;

            return new Response(
                200,
                [],
                json_encode(["message" => "Email sent successfully"]),
            );
        },
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(["handler" => $handlerStack]);

    $transport = new FreesendTransport($this->apiKey, $this->endpoint, $client);

    $email = new Email()
        ->from(new Address("sender@example.com", "John Doe"))
        ->to("recipient@example.com")
        ->subject("Test Subject")
        ->text("Test body");

    $transport->send($email);

    $body = json_decode($capturedRequest->getBody()->getContents(), true);

    expect($body)
        ->toHaveKey("fromEmail", "sender@example.com")
        ->toHaveKey("fromName", "John Doe");
});

it("sends correct authorization header", function () {
    $capturedRequest = null;

    $mock = new MockHandler([
        function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;

            return new Response(
                200,
                [],
                json_encode(["message" => "Email sent successfully"]),
            );
        },
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(["handler" => $handlerStack]);

    $transport = new FreesendTransport($this->apiKey, $this->endpoint, $client);

    $email = new Email()
        ->from("sender@example.com")
        ->to("recipient@example.com")
        ->subject("Test")
        ->text("Test");

    $transport->send($email);

    expect($capturedRequest->getHeader("Authorization")[0])->toBe(
        "Bearer test-api-key",
    );
});

it("throws exception on api error", function () {
    $mock = new MockHandler([
        new Response(401, [], json_encode(["error" => "Invalid API key"])),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(["handler" => $handlerStack]);

    $transport = new FreesendTransport($this->apiKey, $this->endpoint, $client);

    $email = new Email()
        ->from("sender@example.com")
        ->to("recipient@example.com")
        ->subject("Test")
        ->text("Test");

    $transport->send($email);
})->throws(TransportException::class);

it("throws exception when no recipient is provided", function () {
    $mock = new MockHandler([
        new Response(
            200,
            [],
            json_encode(["message" => "Email sent successfully"]),
        ),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(["handler" => $handlerStack]);

    $transport = new FreesendTransport($this->apiKey, $this->endpoint, $client);

    $email = new Email()
        ->from("sender@example.com")
        ->subject("Test")
        ->text("Test");

    $transport->send($email);
})->throws(TransportException::class, "No recipient address provided");

it("handles attachments with base64 encoding", function () {
    $capturedRequest = null;

    $mock = new MockHandler([
        function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;

            return new Response(
                200,
                [],
                json_encode(["message" => "Email sent successfully"]),
            );
        },
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(["handler" => $handlerStack]);

    $transport = new FreesendTransport($this->apiKey, $this->endpoint, $client);

    $email = new Email()
        ->from("sender@example.com")
        ->to("recipient@example.com")
        ->subject("Test")
        ->text("Test")
        ->attach("file content", "document.txt", "text/plain");

    $transport->send($email);

    $body = json_decode($capturedRequest->getBody()->getContents(), true);

    expect($body)
        ->toHaveKey("attachments")
        ->and($body["attachments"])
        ->toHaveCount(1)
        ->and($body["attachments"][0])
        ->toHaveKey("filename", "document.txt")
        ->toHaveKey("content", base64_encode("file content"))
        ->toHaveKey("contentType", "text/plain");
});

it("handles url-based attachments", function () {
    $capturedRequest = null;

    $mock = new MockHandler([
        function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;

            return new Response(
                200,
                [],
                json_encode(["message" => "Email sent successfully"]),
            );
        },
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(["handler" => $handlerStack]);

    $transport = new FreesendTransport($this->apiKey, $this->endpoint, $client);

    $email = new Email()
        ->from("sender@example.com")
        ->to("recipient@example.com")
        ->subject("Test")
        ->text("Test");

    // Add an attachment with the URL header
    $email->attach("", "document.pdf", "application/pdf");
    $attachments = $email->getAttachments();
    $lastAttachment = end($attachments);
    $lastAttachment
        ->getHeaders()
        ->addTextHeader(
            FreesendTransport::URL_ATTACHMENT_HEADER,
            "https://example.com/files/document.pdf",
        );

    $transport->send($email);

    $body = json_decode($capturedRequest->getBody()->getContents(), true);

    expect($body)
        ->toHaveKey("attachments")
        ->and($body["attachments"])
        ->toHaveCount(1)
        ->and($body["attachments"][0])
        ->toHaveKey("filename", "document.pdf")
        ->toHaveKey("url", "https://example.com/files/document.pdf")
        ->not->toHaveKey("content");
});

it("sends both html and text content when provided", function () {
    $capturedRequest = null;

    $mock = new MockHandler([
        function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;

            return new Response(
                200,
                [],
                json_encode(["message" => "Email sent successfully"]),
            );
        },
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(["handler" => $handlerStack]);

    $transport = new FreesendTransport($this->apiKey, $this->endpoint, $client);

    $email = new Email()
        ->from("sender@example.com")
        ->to("recipient@example.com")
        ->subject("Test Subject")
        ->text("Plain text version")
        ->html("<p>HTML version</p>");

    $transport->send($email);

    $body = json_decode($capturedRequest->getBody()->getContents(), true);

    expect($body)
        ->toHaveKey("text", "Plain text version")
        ->toHaveKey("html", "<p>HTML version</p>");
});

it("sends to correct endpoint", function () {
    $capturedRequest = null;
    $customEndpoint = "https://custom.freesend.test/api/send-email";

    $mock = new MockHandler([
        function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;

            return new Response(
                200,
                [],
                json_encode(["message" => "Email sent successfully"]),
            );
        },
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(["handler" => $handlerStack]);

    $transport = new FreesendTransport($this->apiKey, $customEndpoint, $client);

    $email = new Email()
        ->from("sender@example.com")
        ->to("recipient@example.com")
        ->subject("Test")
        ->text("Test");

    $transport->send($email);

    expect((string) $capturedRequest->getUri())->toBe($customEndpoint);
});
