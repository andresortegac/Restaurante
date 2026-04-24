@extends('layouts.app')

@section('title', $invoice->invoice_number . ' - Facturación Electrónica')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Facturación / Factus</span>
                <h1>{{ $invoice->invoice_number }}</h1>
                <p>Detalle de envío, respuesta de Factus, comprobantes XML/PDF y trazabilidad completa de la factura electrónica.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $invoice->status }}</span>
                <span class="summary-chip">{{ $invoice->electronic_number ?: 'Sin número Factus' }}</span>
                <span class="summary-chip">{{ $invoice->cufe ?: 'Sin CUFE' }}</span>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-xl-4">
                <div class="card module-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-circle-info"></i> Resumen</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            <div class="module-list-item">
                                <div>
                                    <strong>Cliente</strong>
                                    <div class="table-note">{{ $invoice->sale?->customer?->name ?: $invoice->sale?->customer_name ?: 'Sin cliente' }}</div>
                                </div>
                            </div>
                            <div class="module-list-item">
                                <div>
                                    <strong>Total</strong>
                                    <div class="table-note">${{ number_format((float) ($invoice->sale?->total ?? 0), 2) }}</div>
                                </div>
                            </div>
                            <div class="module-list-item">
                                <div>
                                    <strong>Estado</strong>
                                    <div class="table-note">{{ $invoice->status_message ?: 'Sin mensaje adicional' }}</div>
                                </div>
                            </div>
                            <div class="module-list-item">
                                <div>
                                    <strong>Último intento</strong>
                                    <div class="table-note">{{ $invoice->last_attempt_at?->format('d/m/Y H:i') ?? 'Sin intentos' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <form method="POST" action="{{ route('electronic-invoices.retry', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-warning">Reintentar</button>
                            </form>
                            <form method="POST" action="{{ route('electronic-invoices.sync', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary">Sincronizar</button>
                            </form>
                            @if($invoice->pdf_path)
                                <a href="{{ route('electronic-invoices.pdf', $invoice) }}" class="btn btn-outline-success">PDF</a>
                            @endif
                            @if($invoice->xml_path)
                                <a href="{{ route('electronic-invoices.xml', $invoice) }}" class="btn btn-outline-secondary">XML</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card module-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-list-check"></i> Trazabilidad</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            @forelse($invoice->logs->sortByDesc('created_at') as $log)
                                <div class="module-list-item">
                                    <div>
                                        <strong>{{ $log->event }}</strong>
                                        <div class="table-note">{{ $log->message }}</div>
                                        <div class="table-note">{{ $log->created_at?->format('d/m/Y H:i:s') }}</div>
                                    </div>
                                    <span class="badge rounded-pill {{ $log->level === 'error' ? 'bg-danger' : ($log->level === 'warning' ? 'bg-warning text-dark' : 'bg-success') }}">{{ $log->level }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">Todavía no hay logs para esta factura.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="card module-card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-code"></i> Respuesta Factus</h5>
                    </div>
                    <div class="card-body">
                        <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($invoice->factus_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
