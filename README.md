# Laravel Freesend

A Laravel mail driver for the [Freesend](https://freesend.metafog.io) email API.

## Installation

Install the package via Composer:

```bash
composer require skylarkltd/laravel-freesend
```

## Configuration

Add a Freesend API key to your `.env` file:

```env
FREESEND_API_KEY=your-api-key-here
```

Add the Freesend mailer to your `config/mail.php` mailers array:

```php
'mailers' => [
    // ... other mailers

    'freesend' => [
        'transport' => 'freesend',
    ],
],
```

To use Freesend as your default mailer, set it in your `.env`:

```env
MAIL_MAILER=freesend
```

### Custom Endpoint

If you need to use a different API endpoint (e.g., a self-hosted instance), you can configure it via environment variable:

```env
FREESEND_ENDPOINT=https://your-custom-endpoint.com/api/send-email
```

### Sender Configuration

Configure the default sender address and name in your `.env` file:

```env
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="My Application"
```

These values are set in Laravel's `config/mail.php`:

```php
'from' => [
    'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    'name' => env('MAIL_FROM_NAME', 'Example'),
],
```

You can also set the sender per-email in your Mailable classes using the `envelope()` method.

## Usage

Once configured, you can use Laravel's standard mail functionality:

### Using Mailables

```php
use App\Mail\WelcomeEmail;
use Illuminate\Support\Facades\Mail;

Mail::to('recipient@example.com')->send(new WelcomeEmail());
```

### Using the Mail Facade Directly

```php
use Illuminate\Support\Facades\Mail;

Mail::raw('Hello, this is a test email!', function ($message) {
    $message->to('recipient@example.com')
            ->subject('Test Email');
});
```

### With Attachments

Standard Laravel attachments are fully supported:

```php
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;

// In your Mailable class:
public function content(): Content
{
    return new Content(
        view: 'emails.invoice',
    );
}

public function attachments(): array
{
    return [
        Attachment::fromPath('/path/to/invoice.pdf'),
        Attachment::fromStorage('reports/monthly.pdf'),
        Attachment::fromData(fn () => $this->pdf, 'invoice.pdf')
            ->withMime('application/pdf'),
    ];
}
```

### URL-Based Attachments

The Freesend API supports fetching attachments directly from URLs, which can be more efficient for large files or files already hosted online. Use the `UrlAttachment` helper:

```php
use Illuminate\Mail\Mailables\Attachment;
use Skylarkltd\Freesend\UrlAttachment;

public function attachments(): array
{
    return [
        // Standard file attachment
        Attachment::fromPath('/path/to/local-file.pdf'),

        // URL-based attachment - Freesend fetches the file from the URL
        UrlAttachment::fromUrl(
            'https://example.com/files/report.pdf',
            'monthly-report.pdf',
            'application/pdf'  // Optional content type
        ),

        // Works great with cloud storage temporary URLs
        UrlAttachment::fromUrl(
            Storage::temporaryUrl('documents/contract.pdf', now()->addMinutes(30)),
            'contract.pdf',
            'application/pdf'
        ),
    ];
}
```

**URL Attachment Limitations:**
- Only HTTP/HTTPS URLs are supported
- Maximum file size: 25MB
- Freesend has a 30-second timeout for fetching URL-based attachments

### Specifying Freesend for a Specific Email

```php
Mail::mailer('freesend')
    ->to('recipient@example.com')
    ->send(new WelcomeEmail());
```

## Full Example

Here's a complete example of a Mailable class using Freesend with all features:

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Skylarkltd\Freesend\UrlAttachment;

class OrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('orders@myapp.com', 'My App Orders'),
            subject: "Order Confirmation #{$this->order->number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.confirmation',
            with: [
                'orderNumber' => $this->order->number,
                'items' => $this->order->items,
                'total' => $this->order->total,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            // Attach a locally generated PDF invoice
            Attachment::fromData(
                fn () => PDF::loadView('invoices.order', ['order' => $this->order])->output(),
                "invoice-{$this->order->number}.pdf"
            )->withMime('application/pdf'),

            // Attach a file from cloud storage via URL
            UrlAttachment::fromUrl(
                Storage::disk('s3')->temporaryUrl(
                    "receipts/{$this->order->receipt_path}",
                    now()->addMinutes(30)
                ),
                'receipt.pdf',
                'application/pdf'
            ),
        ];
    }
}
```

Send the email:

```php
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Mail;

// Send immediately
Mail::to($customer->email)->send(new OrderConfirmation($order));

// Or queue it
Mail::to($customer->email)->queue(new OrderConfirmation($order));
```

## Publishing Configuration

To publish the package configuration file:

```bash
php artisan vendor:publish --tag=freesend-config
```

This will create `config/freesend.php` where you can set the API key and endpoint.

## API Reference

### FreesendTransport

The transport class that handles communication with the Freesend API. It extends Symfony's `AbstractTransport` and integrates with Laravel's mail system.

### UrlAttachment

Helper class for creating URL-based attachments.

```php
UrlAttachment::fromUrl(
    string $url,        // The HTTP/HTTPS URL to the file
    string $filename,   // The filename to use in the email
    ?string $contentType = null  // Optional MIME type
): Attachment
```

## License

MIT License. See [LICENSE](LICENSE) for more information.
