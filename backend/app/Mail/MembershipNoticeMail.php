<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MembershipNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string|int|null>  $details
     */
    public function __construct(
        public readonly string $heading,
        public readonly string $bodyText,
        public readonly array $details,
        public readonly ?string $invoicePdf = null,
        public readonly ?string $invoiceFilename = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.membership-notice',
            with: [
                'heading' => $this->heading,
                'bodyText' => $this->bodyText,
                'details' => $this->details,
                'hasInvoice' => $this->invoicePdf !== null,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->invoicePdf === null || $this->invoiceFilename === null) {
            return [];
        }

        $invoicePdf = $this->invoicePdf;

        return [
            Attachment::fromData(fn (): string => $invoicePdf, $this->invoiceFilename)
                ->withMime('application/pdf'),
        ];
    }
}
