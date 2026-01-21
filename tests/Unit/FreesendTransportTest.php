<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Skylark\Freesend\FreesendTransport;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    $this->apiKey = "test-api-key";
    $this->endpoint = "https://freesend.test/api/send-email";
});

function createEmail(): Email
{
    return new Email();
}

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

    $email = createEmail();
    $email->from("sender@example.com");
    $email->to("recipient@example.com");
    $email->subject("Test Subject");
    $email->text("Test body content");

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

    $email = createEmail();
    $email->from("sender@example.com");
    $email->to("recipient@example.com");
    $email->subject("Test Subject");
    $email->html("<h1>Hello World</h1>");

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

    $email = createEmail();
    $email->from(new Address("sender@example.com", "John Doe"));
    $email->to("recipient@example.com");
    $email->subject("Test Subject");
    $email->text("Test body");

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

    $email = createEmail();
    $email->from("sender@example.com");
    $email->to("recipient@example.com");
    $email->subject("Test");
    $email->text("Test");

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

    $email = createEmail();
    $email->from("sender@example.com");
    $email->to("recipient@example.com");
    $email->subject("Test");
    $email->text("Test");

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

    $email = createEmail();
    $email->from("sender@example.com");
    $email->subject("Test");
    $email->text("Test");

    $transport->send($email);
})->throws(\Symfony\Component\Mime\Exception\LogicException::class);

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

    $email = createEmail();
    $email->from("sender@example.com");
    $email->to("recipient@example.com");
    $email->subject("Test");
    $email->text("Test");
    $email->attach("file content", "document.txt", "text/plain");

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

it("handles url-based attachments via registry", function () {
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

    // Create a URL attachment - this registers the URL in the static registry
    $url = "https://example.com/files/document.pdf";
    \Skylark\Freesend\UrlAttachment::fromUrl(
        $url,
        "document.pdf",
        "application/pdf",
    );

    // Get all registered markers by checking what extractUrl accepts
    // We need to find the marker that was just registered
    // Since we can't access the registry directly, we'll use reflection
    $reflection = new ReflectionClass(\Skylark\Freesend\UrlAttachment::class);
    $registryProperty = $reflection->getProperty("urlRegistry");
    $registryProperty->setAccessible(true);
    $registry = $registryProperty->getValue();

    // Get the marker (should be the only one since we clear in beforeEach... but we don't have that here)
    $marker = array_key_first($registry);

    $email = createEmail();
    $email->from("sender@example.com");
    $email->to("recipient@example.com");
    $email->subject("Test");
    $email->text("Test");
    $email->attach($marker, "document.pdf", "application/pdf");

    $transport->send($email);

    $body = json_decode($capturedRequest->getBody()->getContents(), true);

    expect($body)
        ->toHaveKey("attachments")
        ->and($body["attachments"])
        ->toHaveCount(1)
        ->and($body["attachments"][0])
        ->toHaveKey("filename", "document.pdf")
        ->toHaveKey("url", $url)
        ->not->toHaveKey("content");

    // Clean up
    \Skylark\Freesend\UrlAttachment::clearRegistry();
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

    $email = createEmail();
    $email->from("sender@example.com");
    $email->to("recipient@example.com");
    $email->subject("Test Subject");
    $email->text("Plain text version");
    $email->html("<p>HTML version</p>");

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

    $email = createEmail();
    $email->from("sender@example.com");
    $email->to("recipient@example.com");
    $email->subject("Test");
    $email->text("Test");

    $transport->send($email);

    expect((string) $capturedRequest->getUri())->toBe($customEndpoint);
});
