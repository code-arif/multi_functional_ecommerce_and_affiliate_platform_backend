<?php

namespace Modules\Product\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Product\Models\Product;
use Modules\Vendor\Models\Vendor;

class VendorProductUpdatedAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 3;

    public function __construct(
        public Product $product,
        public Vendor $vendor,
        public bool $requiresReapproval = false
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->requiresReapproval
            ? 'Vendor Updated Product (Re-approval Needed) - '
            : 'Vendor Updated Product - ';

        return new Envelope(
            subject: $prefix . $this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.vendor-product-updated',
            with: [
                'product'            => $this->product->loadMissing(['category', 'brand', 'variants']),
                'vendor'             => $this->vendor->loadMissing(['user']),
                'requiresReapproval' => $this->requiresReapproval,
            ]
        );
    }
}
