<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Factus\ElectronicInvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendElectronicInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $invoiceId
    ) {
    }

    public function handle(ElectronicInvoiceService $service): void
    {
        $invoice = Invoice::query()->find($this->invoiceId);

        if (!$invoice) {
            return;
        }

        $service->send($invoice);
    }
}
