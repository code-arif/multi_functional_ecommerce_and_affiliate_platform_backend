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

class ProductStatusVendorMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 3;

    public function __construct(
        public Product $product,
        public Vendor $vendor,
        public string $status,
        public ?string $reason = null
    ) {}

    public function envelope(): Envelope
    {
        $label = match ($this->status) {
            'active'   => 'Product Approved & Active',
            'inactive' => 'Product Status: Inactive',
            'draft'    => 'Product Status: Draft',
            'pending'  => 'Product Status: Pending Review',
            default    => 'Product Status Updated',
        };

        return new Envelope(
            subject: "{$label} - {$this->product->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vendor.product-status',
            with: [
                'product' => $this->product->loadMissing(['category', 'brand']),
                'vendor'  => $this->vendor->loadMissing(['user']),
                'status'  => $this->status,
                'reason'  => $this->reason,
            ]
        );
    }
}
