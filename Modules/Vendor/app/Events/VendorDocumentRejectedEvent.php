<?php

namespace Modules\Vendor\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Vendor\Models\VendorDocument;

class VendorDocumentRejectedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public VendorDocument $document,
        public string $reason
    ) {}
}
