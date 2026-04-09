@extends('layouts.app')

@section('title', 'Pedido ' . $restaurantTable->name . ' - RestaurantePOS')

@section('content')
@php
    $user = Auth::user();
    $canManageOrders = $user->hasRole('Admin') || $user->hasAnyPermission(['orders.create', 'orders.edit']);
    $statusLabels = ['free' => 'Libre', 'occupied' => 'Ocupada', 'reserved' => 'Reservada'];
    $statusClasses = ['free' => 'status-free', 'occupied' => 'status-occupied', 'reserved' => 'status-reserved'];
    $statusLabel = $statusLabels[$restaurantTable->status] ?? ucfirst($restaurantTable->status);
    $statusClass = $statusClasses[$restaurantTable->status] ?? 'status-free';
    $productCatalog = $availableProducts->map(fn ($product) => [
        'id' => $product->id,
        'name' => $product->name,
        'price' => (float) $product->price,
        'type' => $product->product_type ?: 'simple',
    ])->values();
@endphp

<div class="module-page">
    <section class="module-hero">
        <div>
            <span class="module-kicker">Pedidos por Mesa / RF-08 al RF-10</span>
            <h1>Servicio de {{ $restaurantTable->name }}</h1>
            <p>Toma el pedido en mesa, agrega productos y manda o reimprime la comanda para cocina desde este flujo.</p>
        </div>
        <div class="summary-group">
            <span class="summary-chip">Codigo {{ $restaurantTable->code }}</span>
            <span class="summary-chip">{{ $restaurantTable->area ?: 'Salon principal' }}</span>
            <span class="summary-chip">{{ $statusLabel }}</span>
        </div>
    </section>

    @include('products.partials.form-errors')

    <div class="table-detail-layout">
        <div>
            <div class="card module-card service-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h5 class="mb-1">Pedido actual</h5>
                        <p class="table-note mb-0">
                            @if($openOrder)
                                {{ $openOrder->order_number }} abierto por {{ $openOrder->openedBy->name ?? 'el equipo' }}.
                            @else
                                Esta mesa aun no tiene un pedido abierto.
                            @endif
                        </p>
                    </div>
                    <div class="table-card-actions">
                        @if($openOrder)
                            <a href="{{ route('orders.kitchen-ticket', $openOrder) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-print"></i> Imprimir cocina
                            </a>
                        @endif
                        <span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                </div>
                <div class="card-body">
                    @if($openOrder)
                        <div class="row g-3">
                            <div class="col-md-4"><div class="order-summary-card h-100"><div class="summary-kicker">Subtotal</div><div class="h3 mb-0">${{ number_format((float) $openOrder->subtotal, 2) }}</div></div></div>
                            <div class="col-md-4"><div class="order-summary-card h-100"><div class="summary-kicker">Impuesto</div><div class="h3 mb-0">${{ number_format((float) $openOrder->tax_amount, 2) }}</div></div></div>
                            <div class="col-md-4"><div class="order-summary-card h-100"><div class="summary-kicker">Total</div><div class="h3 mb-0">${{ number_format((float) $openOrder->total, 2) }}</div></div></div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-lg-6">
                                <div class="meta-box h-100">
                                    <div class="summary-kicker">Cliente o referencia</div>
                                    <div class="fw-bold">{{ $openOrder->customer_name ?: 'Sin nombre registrado' }}</div>
                                    <div class="seat-note">{{ $openOrder->notes ?: 'Sin notas en el pedido.' }}</div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="meta-box h-100">
                                    <div class="summary-kicker">Resumen</div>
                                    <div class="fw-bold">{{ $openOrder->items->sum('quantity') }} items</div>
                                    <div class="seat-note">{{ $splitSummary->count() ?: 1 }} cuenta{{ ($splitSummary->count() ?: 1) > 1 ? 's' : '' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mt-4">
                            <table class="table table-hover order-items-table align-middle mb-0">
                                <thead>
                                    <tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Cuenta</th><th class="text-end">Subtotal</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($openOrder->items as $item)
                                        <tr>
                                            <td><strong>{{ $item->product_name }}</strong><div class="table-note">{{ $item->product?->product_type === 'combo' ? 'Combo' : 'Producto del menu' }}</div></td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>${{ number_format((float) $item->unit_price, 2) }}</td>
                                            <td>Cuenta {{ $item->split_group ?: 1 }}</td>
                                            <td class="text-end">${{ number_format((float) $item->subtotal, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty-state py-4">
                            <i class="fas fa-receipt"></i>
                            <h5 class="mb-2">No hay pedido abierto</h5>
                            <p class="mb-0">Registra los productos desde aqui para iniciar el servicio y mandar la comanda a cocina.</p>
                        </div>
                    @endif
                </div>
            </div>

            @if($canManageOrders)
                <div class="card module-card service-card" id="service-flow">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h5 class="mb-1">{{ $openOrder ? 'Agregar productos al pedido' : 'Tomar pedido para la mesa' }}</h5>
                            <p class="table-note mb-0">Al guardar se abrira la impresion de cocina para los productos agregados.</p>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-add-order-row><i class="fas fa-plus"></i> Agregar fila</button>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('orders.store', $restaurantTable) }}">
                            @csrf
                            <div class="form-grid">
                                <div>
                                    <label class="form-label" for="customer_name">Cliente o referencia</label>
                                    <input type="text" class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', $openOrder?->customer_name) }}">
                                </div>
                                <div>
                                    <label class="form-label" for="notes">Notas del pedido</label>
                                    <input type="text" class="form-control" id="notes" name="notes" value="{{ old('notes', $openOrder?->notes) }}">
                                </div>
                            </div>

                            <div class="component-rows mt-4" id="orderRows">
                                @foreach($orderRows as $index => $row)
                                    @php
                                        $selectedId = (int) old('items.' . $index . '.product_id', $row['product_id'] ?? 0);
                                        $selectedProduct = $availableProducts->firstWhere('id', $selectedId);
                                    @endphp
                                    <div class="component-row order-entry-row" data-order-row>
                                        <div class="component-row-grid">
                                            <div class="component-field-large">
                                                <label class="form-label">Producto</label>
                                                <select class="form-select" name="items[{{ $index }}][product_id]" data-order-product>
                                                    <option value="">Selecciona un producto</option>
                                                    @foreach($availableProducts as $product)
                                                        <option value="{{ $product->id }}" @selected($selectedId === $product->id)>{{ $product->name }} - ${{ number_format((float) $product->price, 2) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div><label class="form-label">Cantidad</label><input type="number" class="form-control" name="items[{{ $index }}][quantity]" min="1" value="{{ old('items.' . $index . '.quantity', $row['quantity'] ?? 1) }}" required></div>
                                            <div><label class="form-label">Tipo</label><input type="text" class="form-control" value="{{ $selectedProduct ? ($selectedProduct->product_type === 'combo' ? 'Combo' : 'Producto') : 'Producto' }}" readonly data-order-type></div>
                                            <div class="d-flex align-items-end"><button type="button" class="btn btn-outline-danger w-100" data-remove-order-row>Quitar</button></div>
                                        </div>
                                        <div class="form-help mt-2" data-order-price>
                                            @if($selectedProduct) Precio actual: ${{ number_format((float) $selectedProduct->price, 2) }}. @else Selecciona un producto del menu o un combo disponible. @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="form-actions">
                                <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">Volver a pedidos</a>
                                <button type="submit" class="btn btn-primary">{{ $openOrder ? 'Guardar y mandar a cocina' : 'Crear pedido y mandar a cocina' }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <aside>
            <div class="card module-card service-card">
                <div class="card-header"><h5 class="mb-0">Control del pedido</h5></div>
                <div class="card-body">
                    <div class="meta-box mb-3">
                        <div class="summary-kicker">Area y capacidad</div>
                        <div class="fw-bold">{{ $restaurantTable->area ?: 'Salon principal' }}</div>
                        <div class="seat-note">Hasta {{ $restaurantTable->capacity }} personas</div>
                    </div>

                    <div class="detail-actions">
                        <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">Volver a pedidos</a>
                        <a href="{{ route('tables.show', $restaurantTable) }}" class="btn btn-outline-primary">Ver mesa</a>
                    </div>

                    @if($openOrder && $canManageOrders)
                        <hr>
                        @if($transferTargets->isEmpty())
                            <div class="meta-box mb-3"><div class="seat-note mb-0">No hay otra mesa disponible para transferir este pedido.</div></div>
                        @else
                            <form method="POST" action="{{ route('orders.transfer', $openOrder) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label" for="target_table_id">Mesa destino</label>
                                    <select class="form-select" id="target_table_id" name="target_table_id" required>
                                        <option value="">Selecciona una mesa</option>
                                        @foreach($transferTargets as $targetTable)
                                            <option value="{{ $targetTable->id }}">{{ $targetTable->name }} - {{ $targetTable->code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-outline-primary w-100">Transferir pedido</button>
                            </form>
                        @endif

                        <a href="{{ route('orders.kitchen-ticket', $openOrder) }}" target="_blank" class="btn btn-outline-primary w-100 mt-3">Reimprimir comanda</a>

                        <a href="{{ route('orders.checkout', $openOrder) }}" class="btn btn-success w-100 mt-3">Cobrar y cerrar cuenta</a>
                        @if(!$activeBox)
                            <div class="meta-box mt-3">
                                <div class="summary-kicker">Caja requerida</div>
                                <div class="seat-note mb-0">Abre una caja antes de registrar el pago de esta mesa.</div>
                            </div>
                        @else
                            <div class="meta-box mt-3">
                                <div class="summary-kicker">Caja activa para el cobro</div>
                                <div class="fw-bold">{{ $activeBox->name }}</div>
                                <div class="seat-note mb-0">El checkout usara esta caja para guardar la venta.</div>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('orders.split', $openOrder) }}" class="mt-3">
                            @csrf
                            @method('PUT')
                            <div class="summary-kicker mb-2">Division de cuentas</div>
                            @foreach($openOrder->items as $item)
                                <div class="d-flex gap-2 align-items-center mb-2">
                                    <div class="seat-note flex-grow-1">{{ $item->product_name }}</div>
                                    <select class="form-select form-select-sm" name="split_items[{{ $item->id }}]">
                                        @for($group = 1; $group <= 8; $group++)
                                            <option value="{{ $group }}" @selected((int) old('split_items.' . $item->id, $item->split_group ?: 1) === $group)>Cuenta {{ $group }}</option>
                                        @endfor
                                    </select>
                                </div>
                            @endforeach
                            <button type="submit" class="btn btn-outline-secondary w-100 mt-2">Guardar division</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card module-card service-card">
                <div class="card-header"><h5 class="mb-0">Historial</h5></div>
                <div class="card-body">
                    <div class="history-list">
                        @forelse($recentOrders as $recentOrder)
                            <div class="history-item">
                                <div class="d-flex justify-content-between gap-3">
                                    <div><strong>{{ $recentOrder->order_number }}</strong><div class="seat-note">{{ $recentOrder->created_at->format('d/m/Y H:i') }}</div></div>
                                    <span class="badge rounded-pill {{ $recentOrder->status === 'open' ? 'text-bg-primary' : ($recentOrder->status === 'paid' ? 'text-bg-success' : 'text-bg-secondary') }}">{{ $recentOrder->status === 'open' ? 'Abierto' : ($recentOrder->status === 'paid' ? 'Pagado' : 'Cancelado') }}</span>
                                </div>
                                <div class="mt-3 d-flex justify-content-between gap-3">
                                    <div><div class="summary-kicker">Cliente</div><div class="seat-note">{{ $recentOrder->customer_name ?: 'Sin nombre registrado' }}</div></div>
                                    <div class="text-end"><div class="summary-kicker">Total</div><div class="fw-bold">${{ number_format((float) $recentOrder->total, 2) }}</div></div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-state py-4">
                                <i class="fas fa-clock-rotate-left"></i>
                                <h5 class="mb-2">Sin historial todavia</h5>
                                <p class="mb-0">Los pedidos de esta mesa apareceran aqui.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

@if($canManageOrders)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rowsContainer = document.getElementById('orderRows');
    const addRowButton = document.querySelector('[data-add-order-row]');
    if (!rowsContainer || !addRowButton) return;
    const products = @json($productCatalog);
    const money = value => '$' + Number(value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const typeLabel = type => type === 'combo' ? 'Combo' : 'Producto';
    const options = selectedId => '<option value="">Selecciona un producto</option>' + products.map(product => '<option value="' + product.id + '"' + (String(selectedId || '') === String(product.id) ? ' selected' : '') + '>' + product.name + ' - ' + money(product.price) + '</option>').join('');
    const refresh = row => {
        const productSelect = row.querySelector('[data-order-product]');
        const typeInput = row.querySelector('[data-order-type]');
        const priceLabel = row.querySelector('[data-order-price]');
        const selectedProduct = products.find(product => String(product.id) === String(productSelect.value));
        typeInput.value = selectedProduct ? typeLabel(selectedProduct.type) : 'Producto';
        priceLabel.textContent = selectedProduct ? 'Precio actual: ' + money(selectedProduct.price) + '.' : 'Selecciona un producto del menu o un combo disponible.';
    };
    const template = index => '<div class="component-row order-entry-row" data-order-row><div class="component-row-grid"><div class="component-field-large"><label class="form-label">Producto</label><select class="form-select" name="items[' + index + '][product_id]" data-order-product>' + options('') + '</select></div><div><label class="form-label">Cantidad</label><input type="number" class="form-control" name="items[' + index + '][quantity]" min="1" value="1" required></div><div><label class="form-label">Tipo</label><input type="text" class="form-control" value="Producto" readonly data-order-type></div><div class="d-flex align-items-end"><button type="button" class="btn btn-outline-danger w-100" data-remove-order-row>Quitar</button></div></div><div class="form-help mt-2" data-order-price>Selecciona un producto del menu o un combo disponible.</div></div>';
    addRowButton.addEventListener('click', () => rowsContainer.insertAdjacentHTML('beforeend', template(rowsContainer.querySelectorAll('[data-order-row]').length)));
    rowsContainer.addEventListener('change', event => { if (event.target.matches('[data-order-product]')) refresh(event.target.closest('[data-order-row]')); });
    rowsContainer.addEventListener('click', event => {
        if (!event.target.matches('[data-remove-order-row]')) return;
        const row = event.target.closest('[data-order-row]');
        if (rowsContainer.querySelectorAll('[data-order-row]').length === 1) {
            row.querySelector('[data-order-product]').value = '';
            row.querySelector('input[type="number"]').value = 1;
            refresh(row);
            return;
        }
        row.remove();
    });
    rowsContainer.querySelectorAll('[data-order-row]').forEach(refresh);
});
</script>
@endif
@endsection
