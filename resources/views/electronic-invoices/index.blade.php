@extends('layouts.app')

@section('title', 'Facturacion Electronica - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Facturacion / Factus</span>
                <h1>Facturas electronicas</h1>
                <p>Consulta facturas emitidas, valida si viajaron a Factus y descarga los documentos cuando esten disponibles.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['total'] }} facturas</span>
                <span class="summary-chip">{{ $summary['validated'] }} validadas</span>
                <span class="summary-chip">{{ $summary['pending'] }} pendientes</span>
                <span class="summary-chip">{{ $summary['failed'] }} fallidas</span>
            </div>
        </section>

        <div class="module-toolbar align-items-start">
            <form method="GET" action="{{ route('electronic-invoices.index') }}" class="row g-2 align-items-end flex-grow-1">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label" for="search">Buscar</label>
                    <input
                        type="text"
                        class="form-control"
                        id="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Factura, Factus, CUFE, cliente, documento o email"
                    >
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label" for="status">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        @foreach(['draft' => 'Borrador', 'queued' => 'En cola', 'submitting' => 'Enviando', 'submitted' => 'Enviada', 'validated' => 'Validada', 'failed' => 'Fallida'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label" for="date_from">Desde</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label" for="date_to">Hasta</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-lg-2 col-md-12 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar</button>
                    <a href="{{ route('electronic-invoices.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>

            <form method="POST" action="{{ route('electronic-invoices.sync-pending') }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-rotate"></i> Validar pendientes
                </button>
            </form>
        </div>

        <div class="card module-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Factura</th>
                                <th>Cliente</th>
                                <th>Factus / CUFE</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoices as $invoice)
                                <tr>
                                    <td>
                                        <strong>{{ $invoice->invoice_number }}</strong>
                                        <div class="table-note">{{ $invoice->reference_code ?: 'Sin referencia' }}</div>
                                        <div class="table-note">{{ $invoice->issued_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $invoice->sale?->customer?->name ?: $invoice->sale?->customer_name ?: 'Sin cliente' }}</div>
                                        <div class="table-note">{{ $invoice->sale?->customer?->document_number ?: 'Sin documento' }}</div>
                                        <div class="table-note">{{ $invoice->sale?->customer?->email ?: 'Sin email' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $invoice->electronic_number ?: 'Pendiente' }}</div>
                                        <div class="table-note">{{ $invoice->cufe ?: 'Sin CUFE todavia' }}</div>
                                    </td>
                                    <td>${{ money($invoice->sale?->total ?? 0) }}</td>
                                    <td>
                                        @php
                                            $statusMap = [
                                                'draft' => ['Borrador', 'bg-secondary'],
                                                'queued' => ['En cola', 'bg-info'],
                                                'submitting' => ['Enviando', 'bg-warning text-dark'],
                                                'submitted' => ['Enviada', 'bg-primary'],
                                                'validated' => ['Validada', 'bg-success'],
                                                'failed' => ['Fallida', 'bg-danger'],
                                            ];
                                            [$label, $class] = $statusMap[$invoice->status] ?? ['Desconocido', 'bg-secondary'];
                                        @endphp
                                        <span class="badge rounded-pill {{ $class }}">{{ $label }}</span>
                                        <div class="table-note mt-1">{{ $invoice->status_message ?: 'Sin mensaje' }}</div>
                                    </td>
                                    <td>
                                        <div class="table-actions justify-content-end">
                                            <a href="{{ route('electronic-invoices.show', $invoice) }}" class="btn btn-outline-primary btn-sm">Ver</a>
                                            @if($invoice->electronic_number)
                                                <form method="POST" action="{{ route('electronic-invoices.sync', $invoice) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Validar</button>
                                                </form>
                                                <a href="{{ route('electronic-invoices.pdf', $invoice) }}" class="btn btn-outline-success btn-sm">PDF</a>
                                                <a href="{{ route('electronic-invoices.xml', $invoice) }}" class="btn btn-outline-dark btn-sm">XML</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Todavia no hay facturas electronicas registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $invoices->links() }}
        </div>
    </div>
@endsection
