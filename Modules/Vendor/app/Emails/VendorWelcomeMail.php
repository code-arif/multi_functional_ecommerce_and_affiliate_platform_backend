<?php

namespace Modules\Vendor\Emails;

use Modules\Vendor\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendorWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Vendor $vendor,
        public ?string $password = null
    ) {
        // Mailable::$html (inherited) is used as the raw inline body
        $this->html = $this->buildBody();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vendor Application Received - ' . $this->vendor->shop_name,
        );
    }

    private function buildBody(): string
    {
        $name  = $this->vendor->user?->name ?? $this->vendor->shop_name;
        $email = $this->vendor->user?->email ?? $this->vendor->email;
        $storeName = config('app.name', 'Z-SHOP');

        $lines = [
            "Hi {$name},",
            '',
            "Thank you for applying to sell on {$storeName}! Your vendor account for '{$this->vendor->shop_name}' has been created and received by our compliance team.",
            '',
            'Application Summary:',
            "- Shop Name: {$this->vendor->shop_name}",
            "- Store URL Slug: {$this->vendor->slug}",
            "- Registered Email: {$email}",
            "- Status: Pending Document Verification",
            '',
            'Next Steps:',
            '1. Our merchant verification team will verify your Trade License and identity credentials within 24 business hours.',
            '2. An onboarding specialist may reach out to verify your phone number and warehouse pickup location.',
            '3. Once verified and approved, you will receive an activation email with access to your Seller Center dashboard.',
            '',
            'If you have any questions, feel free to reply to this email.',
            '',
            'Warm regards,',
            "{$storeName} Seller Onboarding Team",
        ];

        return implode(PHP_EOL, $lines);
    }
}
