@extends('layouts.app')

@section('title', 'Domicilios - RestaurantePOS')

@section('content')
    @php
        $canCreateDelivery = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('deliveries.create');
        $canEditDelivery = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('deliveries.edit');
        $canDeleteDelivery = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('deliveries.delete');
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Pedidos / Domicilios</span>
                <h1>Gestion de domicilios</h1>
                <p>Administra pedidos para entrega, asigna domiciliarios y registra el pago del cliente junto con la evidencia de la entrega.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['total'] }} registrados</span>
                <span class="summary-chip">{{ $summary['pending'] }} en proceso</span>
                <span class="summary-chip">{{ $summary['delivered'] }} entregados</span>
                <span class="summary-chip">{{ $summary['cancelled'] }} cancelados</span>
            </div>
        </section>

        <div class="module-toolbar">
            <form method="GET" action="{{ route('deliveries.index') }}" class="row g-2 align-items-end flex-grow-1">
                <div class="col-md-5">
                    <label class="form-label" for="search">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numero, cliente, telefono, direccion o domiciliario">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        @foreach(['pending' => 'Pendiente', 'assigned' => 'Asignado', 'in_transit' => 'En camino', 'delivered' => 'Entregado', 'cancelled' => 'Cancelado'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="delivery_driver_id">Responsable</label>
                    <select class="form-select" id="delivery_driver_id" name="delivery_driver_id">
                        <option value="">Todos</option>
                        @foreach($deliveryDrivers as $deliveryDriver)
                            <option value="{{ $deliveryDriver->id }}" @selected((string) ($filters['delivery_driver_id'] ?? '') === (string) $deliveryDriver->id)>{{ $deliveryDriver->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                    <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
                </div>
            </form>

            <div class="d-flex gap-2 flex-wrap">
                @if($canCreateDelivery)
                    <a href="{{ route('deliveries.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nuevo domicilio
                    </a>
                @endif
            </div>
        </div>

        <div class="card module-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Domicilio</th>
                                <th>Cliente</th>
                                <th>Entrega</th>
                                <th>Cobro</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($deliveries as $delivery)
                                <tr>
                                    <td>
                                        <strong>{{ $delivery->delivery_number }}</strong>
                                        <div class="table-note">{{ $delivery->created_at?->format('d/m/Y H:i') ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $delivery->customer_name }}</div>
                                        <div class="table-note">{{ $delivery->customer_phone ?: 'Sin telefono' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $delivery->deliveryDriver?->name ?? $delivery->assignedUser?->name ?? 'Sin asignar' }}</div>
                                        <div class="table-note">{{ $delivery->delivery_address }}</div>
                                        @if($delivery->delivered_at)
                                            <div class="table-note">Entregado: {{ $delivery->delivered_at->format('d/m/Y H:i') }}</div>
                                        @endif
                                        @if($delivery->delivery_proof_image_url)
                                            <div class="table-note">
                                                <a href="{{ $delivery->delivery_proof_image_url }}" target="_blank" rel="noopener noreferrer">Ver evidencia</a>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>${{ number_format($delivery->total_charge, 2) }}</div>
                                        <div class="table-note">Pedido ${{ number_format($delivery->order_total, 2) }} + envio ${{ number_format($delivery->delivery_fee, 2) }}</div>
                                        <div class="table-note">Paga con ${{ number_format($delivery->customer_payment_amount, 2) }} / Cambio ${{ number_format($delivery->change_required, 2) }}</div>
                                    </td>
                                    <td>
                                        @php
                                            $statusMap = [
                                                'pending' => ['Pendiente', 'bg-secondary'],
                                                'assigned' => ['Asignado', 'bg-info'],
                                                'in_transit' => ['En camino', 'bg-warning text-dark'],
                                                'delivered' => ['Entregado', 'bg-success'],
                                                'cancelled' => ['Cancelado', 'bg-danger'],
                                            ];
                                            [$statusLabel, $statusClass] = $statusMap[$delivery->status];
                                        @endphp
                                        <span class="badge rounded-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td>
                                        <div class="table-actions justify-content-end">
                                            @if($canEditDelivery && $delivery->status !== 'cancelled')
                                                <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#deliveryCompleteModal{{ $delivery->id }}">
                                                    {{ $delivery->status === 'delivered' ? 'Actualizar entrega' : 'Marcar entregado' }}
                                                </button>
                                            @endif
                                            @if($canEditDelivery)
                                                <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-outline-primary btn-sm">Editar</a>
                                            @endif
                                            @if($canDeleteDelivery)
                                                <form method="POST" action="{{ route('deliveries.destroy', $delivery) }}" onsubmit="return confirm('Deseas eliminar este domicilio?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                </form>
                                            @endif
                                        </div>

                                        @if($canEditDelivery && $delivery->status !== 'cancelled')
                                            <div class="modal fade" id="deliveryCompleteModal{{ $delivery->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Registrar entrega</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                        </div>
                                                        <form method="POST" action="{{ route('deliveries.complete', $delivery) }}" enctype="multipart/form-data">
                                                            @csrf
                                                            @method('PUT')
                                                            <div class="modal-body">
                                                                <p class="text-muted mb-3">Este registro se hace desde el sistema local cuando el domiciliario regresa al restaurante y confirma la entrega.</p>
                                                                <div class="mb-3">
                                                                    <label class="form-label" for="delivery_proof_image_{{ $delivery->id }}">Foto de evidencia</label>
                                                                    <input type="file" class="form-control" id="delivery_proof_image_{{ $delivery->id }}" name="delivery_proof_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                                                    <div class="form-text">Opcional. Puedes cargar una foto del cliente, de la fachada o del comprobante.</div>
                                                                </div>
                                                                @if($delivery->delivery_proof_image_url)
                                                                    <div>
                                                                        <a href="{{ $delivery->delivery_proof_image_url }}" target="_blank" rel="noopener noreferrer">Ver evidencia actual</a>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                <button type="submit" class="btn btn-success">Confirmar entrega</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Todavia no hay domicilios registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $deliveries->links() }}
        </div>
    </div>
@endsection
