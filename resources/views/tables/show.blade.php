@extends('layouts.app')

@section('title', $restaurantTable->name . ' - Mesas - RestaurantePOS')

@section('content')
    @php
        $user = Auth::user();
        $canEditTable = $user->hasRole('Admin') || $user->hasPermission('tables.edit');
        $canManageOrders = $user->hasRole('Admin') || $user->hasAnyPermission(['orders.create', 'orders.edit', 'tables.edit']);
        $statusLabels = [
            'free' => 'Libre',
            'occupied' => 'Ocupada',
            'reserved' => 'Reservada',
        ];
        $statusClasses = [
            'free' => 'status-free',
            'occupied' => 'status-occupied',
            'reserved' => 'status-reserved',
        ];
        $statusLabel = $statusLabels[$restaurantTable->status] ?? ucfirst($restaurantTable->status);
        $statusClass = $statusClasses[$restaurantTable->status] ?? 'status-free';
        $productCatalog = $availableProducts->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'type' => $product->product_type ?: 'simple',
            ];
        })->values();
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Mesas / RF-08 al RF-10</span>
                <h1>{{ $restaurantTable->name }}</h1>
                <p>Asigna pedidos a la mesa, agrega nuevos productos al servicio actual, transfiere la orden a otra mesa y organiza la division de cuentas antes del cierre.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">Codigo {{ $restaurantTable->code }}</span>
                <span class="summary-chip">{{ $restaurantTable->area ?: 'Salon principal' }}</span>
                <span class="summary-chip">Capacidad {{ $restaurantTable->capacity }}</span>
                <span class="summary-chip">{{ $statusLabel }}</span>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="table-detail-layout">
            <div>
                <div class="card module-card service-card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h5 class="mb-1">Servicio actual</h5>
                            <p class="table-note mb-0">
                                @if($openOrder)
                                    Pedido {{ $openOrder->order_number }} abierto por {{ $openOrder->openedBy->name ?? 'el equipo' }}.
                                @else
                                    Esta mesa aun no tiene un pedido abierto.
                                @endif
                            </p>
                        </div>
                        <span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                    <div class="card-body">
                        @if($openOrder)
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="order-summary-card h-100">
                                        <div class="summary-kicker">Subtotal</div>
                                        <div class="h3 mb-0">${{ number_format((float) $openOrder->subtotal, 2) }}</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="order-summary-card h-100">
                                        <div class="summary-kicker">Impuesto</div>
                                        <div class="h3 mb-0">${{ number_format((float) $openOrder->tax_amount, 2) }}</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="order-summary-card h-100">
                                        <div class="summary-kicker">Total</div>
                                        <div class="h3 mb-0">${{ number_format((float) $openOrder->total, 2) }}</div>
                                    </div>
                                </div>
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
                                        <div class="summary-kicker">Movimiento</div>
                                        <div class="fw-bold">
                                            {{ $openOrder->items->sum('quantity') }} items
                                            en {{ $splitSummary->count() ?: 1 }} cuenta{{ ($splitSummary->count() ?: 1) > 1 ? 's' : '' }}
                                        </div>
                                        <div class="seat-note">
                                            @if($openOrder->last_transferred_at)
                                                Transferido por ultima vez el {{ $openOrder->last_transferred_at->format('d/m/Y H:i') }}.
                                            @else
                                                Pedido creado el {{ $openOrder->created_at->format('d/m/Y H:i') }}.
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive mt-4">
                                <table class="table table-hover order-items-table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Precio unitario</th>
                                            <th>Cuenta</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($openOrder->items as $item)
                                            <tr>
                                                <td>
                                                    <strong>{{ $item->product_name }}</strong>
                                                    <div class="table-note">{{ $item->product?->product_type === 'combo' ? 'Combo' : 'Producto del menu' }}</div>
                                                </td>
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
                                <p class="mb-0">Cuando agregues productos desde el formulario de servicio, la mesa pasara a estado ocupada automaticamente.</p>
                            </div>
                        @endif
                    </div>
                </div>

                @if($canManageOrders)
                    <div class="card module-card service-card" id="service-flow">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div>
                                <h5 class="mb-1">{{ $openOrder ? 'Agregar productos al pedido' : 'Asignar pedido a la mesa' }}</h5>
                                <p class="table-note mb-0">Selecciona productos del menu o combos, indica cantidades y guarda para crear o ampliar el servicio.</p>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-add-order-row>
                                <i class="fas fa-plus"></i> Agregar fila
                            </button>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('tables.orders.store', $restaurantTable) }}">
                                @csrf

                                <div class="form-grid">
                                    <div>
                                        <label class="form-label" for="customer_name">Cliente o referencia</label>
                                        <input type="text" class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', $openOrder?->customer_name) }}" placeholder="Ejemplo: Mesa familiar o Reserva Gomez">
                                    </div>

                                    <div>
                                        <label class="form-label" for="notes">Notas del pedido</label>
                                        <input type="text" class="form-control" id="notes" name="notes" value="{{ old('notes', $openOrder?->notes) }}" placeholder="Alergias, observaciones o turno.">
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
                                                            <option value="{{ $product->id }}" @selected($selectedId === $product->id)>
                                                                {{ $product->name }} - ${{ number_format((float) $product->price, 2) }} - {{ $product->product_type === 'combo' ? 'Combo' : 'Producto' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="form-label">Cantidad</label>
                                                    <input type="number" class="form-control" name="items[{{ $index }}][quantity]" min="1" value="{{ old('items.' . $index . '.quantity', $row['quantity'] ?? 1) }}" required>
                                                </div>

                                                <div>
                                                    <label class="form-label">Tipo</label>
                                                    <input type="text" class="form-control" value="{{ $selectedProduct ? ($selectedProduct->product_type === 'combo' ? 'Combo' : 'Producto') : 'Producto' }}" readonly data-order-type>
                                                </div>

                                                <div class="d-flex align-items-end">
                                                    <button type="button" class="btn btn-outline-danger w-100" data-remove-order-row>Quitar</button>
                                                </div>
                                            </div>

                                            <div class="form-help mt-2" data-order-price>
                                                @if($selectedProduct)
                                                    Precio actual: ${{ number_format((float) $selectedProduct->price, 2) }}.
                                                @else
                                                    Selecciona un producto del menu o un combo disponible.
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="form-actions">
                                    <a href="{{ route('tables.index') }}" class="btn btn-outline-secondary">Volver</a>
                                    <button type="submit" class="btn btn-primary">{{ $openOrder ? 'Agregar al pedido' : 'Crear pedido' }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                @if($openOrder && $canManageOrders)
                    <div class="card module-card service-card">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div>
                                <h5 class="mb-1">Dividir cuentas</h5>
                                <p class="table-note mb-0">Asigna cada item a una cuenta independiente para separar el cobro por grupos.</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('tables.orders.split', $openOrder) }}">
                                @csrf
                                @method('PUT')

                                <div class="table-responsive">
                                    <table class="table table-hover order-items-table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Cantidad</th>
                                                <th>Subtotal</th>
                                                <th>Cuenta</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($openOrder->items as $item)
                                                <tr>
                                                    <td>
                                                        <strong>{{ $item->product_name }}</strong>
                                                        <div class="table-note">${{ number_format((float) $item->unit_price, 2) }} cada uno</div>
                                                    </td>
                                                    <td>{{ $item->quantity }}</td>
                                                    <td>${{ number_format((float) $item->subtotal, 2) }}</td>
                                                    <td>
                                                        <select class="form-select" name="split_items[{{ $item->id }}]">
                                                            @for($group = 1; $group <= 8; $group++)
                                                                <option value="{{ $group }}" @selected((int) old('split_items.' . $item->id, $item->split_group ?: 1) === $group)>
                                                                    Cuenta {{ $group }}
                                                                </option>
                                                            @endfor
                                                        </select>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="split-actions mt-3">
                                    <button type="submit" class="btn btn-outline-primary">Guardar division</button>
                                </div>
                            </form>

                            <div class="split-summary-grid mt-4">
                                @forelse($splitSummary as $account)
                                    <div class="split-summary-card">
                                        <div class="summary-kicker">Cuenta {{ $account['group'] }}</div>
                                        <div class="h5 mb-2">${{ number_format((float) $account['total'], 2) }}</div>
                                        <div class="seat-note">{{ $account['items_count'] }} items</div>
                                        <div class="table-note">
                                            Subtotal ${{ number_format((float) $account['subtotal'], 2) }}<br>
                                            Impuesto ${{ number_format((float) $account['tax_amount'], 2) }}
                                        </div>
                                    </div>
                                @empty
                                    <div class="meta-box">
                                        Toda la cuenta esta unificada en un solo grupo.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <aside>
                <div class="card module-card service-card">
                    <div class="card-header">
                        <h5 class="mb-0">Control de mesa</h5>
                    </div>
                    <div class="card-body">
                        <div class="meta-box mb-3">
                            <div class="summary-kicker">Estado operativo</div>
                            <div class="fw-bold">{{ $statusLabel }}</div>
                            <div class="seat-note">{{ $restaurantTable->is_active ? 'Mesa visible en operacion' : 'Mesa inactiva para el equipo' }}</div>
                        </div>

                        <div class="meta-box mb-3">
                            <div class="summary-kicker">Area y capacidad</div>
                            <div class="fw-bold">{{ $restaurantTable->area ?: 'Salon principal' }}</div>
                            <div class="seat-note">Hasta {{ $restaurantTable->capacity }} personas</div>
                        </div>

                        <div class="meta-box mb-4">
                            <div class="summary-kicker">Notas</div>
                            <div class="seat-note">{{ $restaurantTable->notes ?: 'Sin notas operativas registradas.' }}</div>
                        </div>

                        <div class="detail-actions">
                            <a href="{{ route('tables.index') }}" class="btn btn-outline-secondary">Volver al salon</a>

                            @if($canEditTable)
                                <a href="{{ route('tables.edit', $restaurantTable) }}" class="btn btn-outline-primary">Editar mesa</a>
                            @endif
                        </div>

                        @if($openOrder && $canManageOrders)
                            <hr>

                            <div class="summary-kicker mb-2">Transferir pedido</div>
                            @if($transferTargets->isEmpty())
                                <div class="meta-box mb-3">
                                    <div class="seat-note mb-0">No hay otra mesa libre o reservada disponible para recibir este pedido.</div>
                                </div>
                            @else
                                <form method="POST" action="{{ route('tables.orders.transfer', $openOrder) }}">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label" for="target_table_id">Mesa destino</label>
                                        <select class="form-select" id="target_table_id" name="target_table_id" required>
                                            <option value="">Selecciona una mesa</option>
                                            @foreach($transferTargets as $targetTable)
                                                <option value="{{ $targetTable->id }}">
                                                    {{ $targetTable->name }} - {{ $targetTable->code }} - {{ $targetTable->area ?: 'Salon principal' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-outline-primary w-100">Transferir pedido</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('tables.orders.close', $openOrder) }}" class="mt-3" onsubmit="return confirm('Deseas cerrar la cuenta y liberar esta mesa?');">
                                @csrf
                                <button type="submit" class="btn btn-success w-100">Cerrar cuenta y liberar mesa</button>
                            </form>
                        @elseif($canManageOrders)
                            <hr>
                            <div class="meta-box">
                                <div class="summary-kicker">Siguiente paso</div>
                                <div class="seat-note mb-0">Usa el formulario de servicio para crear el primer pedido y dejar la mesa ocupada automaticamente.</div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card module-card service-card">
                    <div class="card-header">
                        <h5 class="mb-0">Historial de pedidos</h5>
                    </div>
                    <div class="card-body">
                        <div class="history-list">
                            @forelse($recentOrders as $recentOrder)
                                <div class="history-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <strong>{{ $recentOrder->order_number }}</strong>
                                            <div class="seat-note">{{ $recentOrder->created_at->format('d/m/Y H:i') }}</div>
                                        </div>
                                        <span class="badge rounded-pill {{ $recentOrder->status === 'open' ? 'text-bg-primary' : ($recentOrder->status === 'paid' ? 'text-bg-success' : 'text-bg-secondary') }}">
                                            {{ $recentOrder->status === 'open' ? 'Abierto' : ($recentOrder->status === 'paid' ? 'Pagado' : 'Cancelado') }}
                                        </span>
                                    </div>

                                    <div class="mt-3">
                                        <div class="summary-kicker">Cliente</div>
                                        <div class="seat-note">{{ $recentOrder->customer_name ?: 'Sin nombre registrado' }}</div>
                                    </div>

                                    <div class="mt-3 d-flex justify-content-between gap-3">
                                        <div>
                                            <div class="summary-kicker">Total</div>
                                            <div class="fw-bold">${{ number_format((float) $recentOrder->total, 2) }}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="summary-kicker">Items</div>
                                            <div class="fw-bold">{{ $recentOrder->items->sum('quantity') }}</div>
                                        </div>
                                    </div>

                                    @if($recentOrder->previousTable)
                                        <div class="table-note mt-3">Transferido desde {{ $recentOrder->previousTable->name }}.</div>
                                    @endif
                                </div>
                            @empty
                                <div class="empty-state py-4">
                                    <i class="fas fa-clock-rotate-left"></i>
                                    <h5 class="mb-2">Sin historial todavia</h5>
                                    <p class="mb-0">Cuando esta mesa tenga pedidos cerrados o abiertos, apareceran en este panel.</p>
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

                if (!rowsContainer || !addRowButton) {
                    return;
                }

                const products = @json($productCatalog);

                function formatCurrency(value) {
                    return '$' + Number(value || 0).toLocaleString('es-CO', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }

                function typeLabel(type) {
                    return type === 'combo' ? 'Combo' : 'Producto';
                }

                function productOptions(selectedId) {
                    const baseOption = '<option value="">Selecciona un producto</option>';
                    const options = products.map(function (product) {
                        const isSelected = String(selectedId || '') === String(product.id) ? ' selected' : '';
                        return '<option value="' + product.id + '"' + isSelected + '>' +
                            product.name + ' - ' + formatCurrency(product.price) + ' - ' + typeLabel(product.type) +
                            '</option>';
                    }).join('');

                    return baseOption + options;
                }

                function updateRow(row) {
                    const productSelect = row.querySelector('[data-order-product]');
                    const typeInput = row.querySelector('[data-order-type]');
                    const priceLabel = row.querySelector('[data-order-price]');

                    if (!productSelect || !typeInput || !priceLabel) {
                        return;
                    }

                    const selectedProduct = products.find(function (product) {
                        return String(product.id) === String(productSelect.value);
                    });

                    if (!selectedProduct) {
                        typeInput.value = 'Producto';
                        priceLabel.textContent = 'Selecciona un producto del menu o un combo disponible.';
                        return;
                    }

                    typeInput.value = typeLabel(selectedProduct.type);
                    priceLabel.textContent = 'Precio actual: ' + formatCurrency(selectedProduct.price) + '.';
                }

                function rowTemplate(index) {
                    return '' +
                        '<div class="component-row order-entry-row" data-order-row>' +
                            '<div class="component-row-grid">' +
                                '<div class="component-field-large">' +
                                    '<label class="form-label">Producto</label>' +
                                    '<select class="form-select" name="items[' + index + '][product_id]" data-order-product>' +
                                        productOptions('') +
                                    '</select>' +
                                '</div>' +
                                '<div>' +
                                    '<label class="form-label">Cantidad</label>' +
                                    '<input type="number" class="form-control" name="items[' + index + '][quantity]" min="1" value="1" required>' +
                                '</div>' +
                                '<div>' +
                                    '<label class="form-label">Tipo</label>' +
                                    '<input type="text" class="form-control" value="Producto" readonly data-order-type>' +
                                '</div>' +
                                '<div class="d-flex align-items-end">' +
                                    '<button type="button" class="btn btn-outline-danger w-100" data-remove-order-row>Quitar</button>' +
                                '</div>' +
                            '</div>' +
                            '<div class="form-help mt-2" data-order-price>Selecciona un producto del menu o un combo disponible.</div>' +
                        '</div>';
                }

                function nextIndex() {
                    return rowsContainer.querySelectorAll('[data-order-row]').length;
                }

                addRowButton.addEventListener('click', function () {
                    rowsContainer.insertAdjacentHTML('beforeend', rowTemplate(nextIndex()));
                });

                rowsContainer.addEventListener('change', function (event) {
                    if (event.target.matches('[data-order-product]')) {
                        updateRow(event.target.closest('[data-order-row]'));
                    }
                });

                rowsContainer.addEventListener('click', function (event) {
                    if (!event.target.matches('[data-remove-order-row]')) {
                        return;
                    }

                    const rows = rowsContainer.querySelectorAll('[data-order-row]');
                    const row = event.target.closest('[data-order-row]');

                    if (rows.length === 1) {
                        const productSelect = row.querySelector('[data-order-product]');
                        const quantityInput = row.querySelector('input[type="number"]');
                        const typeInput = row.querySelector('[data-order-type]');
                        const priceLabel = row.querySelector('[data-order-price]');

                        if (productSelect) {
                            productSelect.value = '';
                        }

                        if (quantityInput) {
                            quantityInput.value = 1;
                        }

                        if (typeInput) {
                            typeInput.value = 'Producto';
                        }

                        if (priceLabel) {
                            priceLabel.textContent = 'Selecciona un producto del menu o un combo disponible.';
                        }

                        return;
                    }

                    row.remove();
                });

                rowsContainer.querySelectorAll('[data-order-row]').forEach(updateRow);
            });
        </script>
    @endif
@endsection
