<?php

namespace Modules\Vendor\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Vendor\Models\Vendor;

class NewVendorAdminAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 3;

    public function __construct(
        public Vendor $vendor
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Vendor Application - ' . $this->vendor->shop_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.new-vendor-alert',
            with: [
                'vendor' => $this->vendor->loadMissing(['user', 'profile', 'addresses']),
            ]
        );
    }
}
