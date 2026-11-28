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

class NewProductAdminAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 3;

    public function __construct(
        public Product $product,
        public Vendor $vendor,
        public bool $isReapproval = false
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->isReapproval ? 'Product Update Needs Re-approval - ' : 'New Product Added - ';

        return new Envelope(
            subject: $prefix . $this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.new-product-alert',
            with: [
                'product'      => $this->product->loadMissing(['category', 'brand', 'variants']),
                'vendor'       => $this->vendor->loadMissing(['user']),
                'isReapproval' => $this->isReapproval,
            ]
        );
    }
}
