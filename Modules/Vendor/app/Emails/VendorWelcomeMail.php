<?php

namespace Modules\Vendor\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Vendor\Models\Vendor;

class VendorWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Vendor $vendor,
        public ?string $password = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vendor Application Received - ' . $this->vendor->shop_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vendor.welcome',
            with: [
                'vendor'   => $this->vendor->loadMissing(['user', 'profile', 'addresses']),
                'password' => $this->password,
            ]
        );
    }
}
