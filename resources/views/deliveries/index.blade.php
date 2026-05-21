@extends('layouts.app')

@section('title', 'Domicilios - RestaurantePOS')

@section('content')
    @php
        $canCreateDelivery = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('deliveries.create');
        $canEditDelivery = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('deliveries.edit');
        $canDeleteDelivery = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('deliveries.delete');
        $canChargeDelivery = $canEditDelivery;
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Pedidos / Domicilios</span>
                <h1>Gestion de domicilios</h1>
                <p>Administra pedidos para entrega, asigna domiciliarios y permite que caja cobre el domicilio con cambio, ticket y evidencia de entrega.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['total'] }} registrados</span>
                <span class="summary-chip">{{ $summary['active'] }} activos</span>
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
                        @foreach(['active' => 'Activo', 'delivered' => 'Entregado', 'cancelled' => 'Cancelado'] as $value => $label)
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
                                @php
                                    $isCharged = (bool) $delivery->sale_id;
                                    $sale = $delivery->sale;
                                    $primaryPayment = $sale?->payments->first();
                                @endphp
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
                                        <div>${{ money($delivery->total_charge) }}</div>
                                        <div class="table-note">Pedido ${{ money($delivery->order_total) }} + envio ${{ money($delivery->delivery_fee) }}</div>
                                        <div class="table-note">Paga con ${{ money($delivery->customer_payment_amount) }} / Cambio ${{ money($delivery->change_required) }}</div>
                                        <div class="table-note">
                                            @if($isCharged)
                                                Cobrado en caja
                                                @if($primaryPayment)
                                                    con {{ $primaryPayment->paymentMethod?->name ?? 'metodo registrado' }}
                                                @endif
                                            @else
                                                Pendiente por cobrar
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $statusMap = [
                                                'active' => ['Activo', 'bg-primary'],
                                                'delivered' => ['Entregado', 'bg-success'],
                                                'cancelled' => ['Cancelado', 'bg-danger'],
                                            ];
                                            [$statusLabel, $statusClass] = $statusMap[$delivery->status] ?? ['Desconocido', 'bg-secondary'];
                                        @endphp
                                        <span class="badge rounded-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td>
                                        <div class="table-actions justify-content-end">
                                            @if($canChargeDelivery && $delivery->status !== 'cancelled' && ! $isCharged)
                                                <a href="{{ route('deliveries.checkout', $delivery) }}" class="btn btn-outline-dark btn-sm delivery-action-btn">Cobrar</a>
                                            @endif
                                            @if($isCharged && $sale)
                                                <a href="{{ route('pos.sales.print', $sale) }}" target="_blank" class="btn btn-outline-secondary btn-sm delivery-action-btn">Ticket</a>
                                            @endif
                                            @if($canEditDelivery && $delivery->status !== 'cancelled')
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-success btn-sm delivery-action-btn js-delivery-complete-toggle"
                                                    data-target="deliveryCompletePanel{{ $delivery->id }}"
                                                    aria-expanded="false"
                                                    aria-controls="deliveryCompletePanel{{ $delivery->id }}"
                                                >
                                                    {{ $delivery->status === 'delivered' ? 'Actualizar entrega' : 'Marcar entregado' }}
                                                </button>
                                            @endif
                                            @if($canEditDelivery && ! $isCharged)
                                                <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-outline-primary btn-sm delivery-action-btn">Editar</a>
                                            @endif
                                            @if($canDeleteDelivery && ! $isCharged)
                                                <form method="POST" action="{{ route('deliveries.destroy', $delivery) }}" class="delivery-action-form" onsubmit="return confirm('Deseas eliminar este domicilio?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm delivery-action-btn">Eliminar</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @if($canEditDelivery && $delivery->status !== 'cancelled')
                                    <tr id="deliveryCompletePanel{{ $delivery->id }}" class="delivery-complete-row d-none">
                                        <td colspan="6">
                                            <div class="delivery-complete-panel">
                                                <div class="delivery-complete-panel__header">
                                                    <div>
                                                        <h5 class="mb-1">Registrar entrega</h5>
                                                        <p class="table-note mb-0">Este registro se hace desde el sistema local cuando el domiciliario regresa al restaurante y confirma la entrega.</p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary btn-sm delivery-action-btn js-delivery-complete-close"
                                                        data-target="deliveryCompletePanel{{ $delivery->id }}"
                                                    >
                                                        Cerrar
                                                    </button>
                                                </div>
                                                <form method="POST" action="{{ route('deliveries.complete', $delivery) }}" enctype="multipart/form-data" class="delivery-complete-form">
                                                    @csrf
                                                    @method('PUT')
                                                    <div>
                                                        <label class="form-label" for="delivery_proof_image_{{ $delivery->id }}">Foto de evidencia</label>
                                                        <input type="file" class="form-control" id="delivery_proof_image_{{ $delivery->id }}" name="delivery_proof_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                                        <div class="form-text">Opcional. Puedes cargar una foto del cliente, de la fachada o del comprobante.</div>
                                                    </div>
                                                    @if($delivery->delivery_proof_image_url)
                                                        <div class="delivery-complete-proof">
                                                            <a href="{{ $delivery->delivery_proof_image_url }}" target="_blank" rel="noopener noreferrer">Ver evidencia actual</a>
                                                        </div>
                                                    @endif
                                                    <div class="delivery-complete-actions">
                                                        <button type="button" class="btn btn-outline-secondary delivery-action-btn js-delivery-complete-close" data-target="deliveryCompletePanel{{ $delivery->id }}">Cancelar</button>
                                                        <button type="submit" class="btn btn-success delivery-action-btn">Confirmar entrega</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
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

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rows = document.querySelectorAll('.delivery-complete-row');

            function closeAllPanels() {
                rows.forEach(function (row) {
                    row.classList.add('d-none');
                });

                document.querySelectorAll('.js-delivery-complete-toggle').forEach(function (button) {
                    button.setAttribute('aria-expanded', 'false');
                });
            }

            document.querySelectorAll('.js-delivery-complete-toggle').forEach(function (button) {
                button.addEventListener('click', function () {
                    const targetId = this.dataset.target;
                    const target = document.getElementById(targetId);
                    const isOpen = target && !target.classList.contains('d-none');

                    closeAllPanels();

                    if (!target || isOpen) {
                        return;
                    }

                    target.classList.remove('d-none');
                    this.setAttribute('aria-expanded', 'true');
                });
            });

            document.querySelectorAll('.js-delivery-complete-close').forEach(function (button) {
                button.addEventListener('click', function () {
                    closeAllPanels();
                });
            });
        });
    </script>
@endsection
