<?php

namespace Modules\Vendor\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Vendor\Models\VendorDocument;

class VendorDocumentRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 3;

    public function __construct(
        public VendorDocument $document,
        public string $reason
    ) {}

    public function envelope(): Envelope
    {
        $docLabel = strtoupper(str_replace('_', ' ', $this->document->type));

        return new Envelope(
            subject: "Action Required: KYC Document Rejected ({$docLabel}) - " . ($this->document->vendor?->shop_name ?? 'Seller Account'),
        );
    }

    public function content(): Content
    {
        $document = $this->document->loadMissing(['vendor.user']);

        return new Content(
            view: 'emails.vendor.document-rejected',
            with: [
                'document' => $document,
                'vendor'   => $document->vendor,
                'reason'   => $this->reason,
            ]
        );
    }
}
