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
        'imageUrl' => $product->image_url,
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
                                    <div class="summary-kicker">Cliente</div>
                                    <div class="fw-bold">{{ $openOrder->customer?->name ?: $openOrder->customer_name ?: 'Sin cliente' }}</div>
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
                        <form method="POST" action="{{ route('orders.store', $restaurantTable) }}" id="orderServiceForm">
                            @csrf
                            <div class="form-grid">
                                <div>
                                    <label class="form-label" for="customer_search">Cliente</label>
                                    <input type="search" class="form-control mb-2" id="customer_search" placeholder="Buscar cliente por nombre, documento, telefono o email">
                                    <select class="form-select" id="customer_id" name="customer_id">
                                        <option value="">Sin cliente</option>
                                        @foreach($availableCustomers as $customer)
                                            <option
                                                value="{{ $customer->id }}"
                                                data-search="{{ \Illuminate\Support\Str::lower(trim($customer->name . ' ' . $customer->document_number . ' ' . $customer->phone . ' ' . $customer->email)) }}"
                                                @selected((string) old('customer_id', $openOrder?->customer_id) === (string) $customer->id)
                                            >
                                                {{ $customer->name }}@if($customer->document_number) - {{ $customer->document_number }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-help mt-2" id="customerSelectionHelp">
                                        @if(old('customer_id', $openOrder?->customer_id))
                                            Cliente seleccionado: {{ $openOrder?->customer?->name ?: 'Cliente cargado' }}.
                                        @else
                                            Se usara la opcion sin cliente a menos que selecciones uno.
                                        @endif
                                    </div>
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
                                                <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $selectedId ?: '' }}" data-order-product>
                                                <button type="button" class="btn btn-outline-primary w-100 text-start d-flex justify-content-between align-items-center" data-open-product-modal>
                                                    <span data-order-product-label>{{ $selectedProduct ? $selectedProduct->name . ' - $' . number_format((float) $selectedProduct->price, 2) : 'Selecciona un producto' }}</span>
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                            <div><label class="form-label">Cantidad</label><input type="number" class="form-control" name="items[{{ $index }}][quantity]" min="1" value="{{ old('items.' . $index . '.quantity', $row['quantity'] ?? 1) }}" required></div>
                                            <div><label class="form-label">Tipo</label><input type="text" class="form-control" value="{{ $selectedProduct ? ($selectedProduct->product_type === 'combo' ? 'Combo' : 'Producto') : 'Producto' }}" readonly data-order-type></div>
                                            <div class="d-flex align-items-end"><button type="button" class="btn btn-outline-danger w-100" data-remove-order-row>Quitar</button></div>
                                        </div>
                                        <div class="form-help mt-2" data-order-price>
                                            @if($selectedProduct) Precio actual: ${{ number_format((float) $selectedProduct->price, 2) }}. @else Selecciona un producto del menu o un combo disponible. @endif
                                        </div>
                                        <div class="mt-3" data-order-preview>
                                            @if($selectedProduct && $selectedProduct->image_url)
                                                <img src="{{ $selectedProduct->image_url }}" alt="{{ $selectedProduct->name }}" style="width: 96px; height: 96px; object-fit: cover; border-radius: 16px; border: 1px solid #dbe3f1;">
                                            @elseif($selectedProduct)
                                                <div style="width: 96px; height: 96px; border-radius: 16px; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            @endif
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

        <div class="modal fade" id="productPickerModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-1">Seleccionar producto</h5>
                            <div class="table-note">Busca por nombre y elige el producto con apoyo visual.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="search" class="form-control mb-3" id="productPickerSearch" placeholder="Filtrar producto...">
                        <div id="productPickerResults" class="row g-3"></div>
                    </div>
                </div>
            </div>
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

        </aside>
    </div>
</div>

@if($canManageOrders)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rowsContainer = document.getElementById('orderRows');
    const addRowButton = document.querySelector('[data-add-order-row]');
    const orderServiceForm = document.getElementById('orderServiceForm');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const productPickerModalElement = document.getElementById('productPickerModal');
    const productPickerSearch = document.getElementById('productPickerSearch');
    const productPickerResults = document.getElementById('productPickerResults');
    const customerSearch = document.getElementById('customer_search');
    const customerSelect = document.getElementById('customer_id');
    const customerSelectionHelp = document.getElementById('customerSelectionHelp');
    const customerOptions = customerSelect ? Array.from(customerSelect.options).slice(1).map(option => ({
        value: option.value,
        label: option.textContent,
        search: option.dataset.search || '',
    })) : [];

    const updateCustomerHelp = () => {
        if (!customerSelect || !customerSelectionHelp) {
            return;
        }

        const selectedOption = customerSelect.options[customerSelect.selectedIndex];

        if (!selectedOption || selectedOption.value === '') {
            customerSelectionHelp.textContent = 'Se usara la opcion sin cliente a menos que selecciones uno.';
            return;
        }

        customerSelectionHelp.textContent = 'Cliente seleccionado: ' + selectedOption.textContent + '.';
    };

    const renderCustomerOptions = searchTerm => {
        if (!customerSelect) {
            return;
        }

        const normalizedSearch = (searchTerm || '')
            .toString()
            .trim()
            .toLowerCase();
        const currentValue = customerSelect.value;
        const filteredOptions = normalizedSearch === ''
            ? customerOptions
            : customerOptions.filter(option => option.search.includes(normalizedSearch));

        customerSelect.innerHTML = '<option value="">Sin cliente</option>' + filteredOptions.map(option => '<option value="' + option.value + '"' + (option.value === currentValue ? ' selected' : '') + '>' + option.label + '</option>').join('');

        if (currentValue && !filteredOptions.some(option => option.value === currentValue)) {
            customerSelect.value = '';
        }

        updateCustomerHelp();
    };

    if (orderServiceForm) {
        orderServiceForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            const submitButton = orderServiceForm.querySelector('button[type="submit"]');
            const originalLabel = submitButton ? submitButton.innerHTML : '';

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            }

            try {
                const response = await fetch(orderServiceForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: new FormData(orderServiceForm),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const validationMessages = data?.errors
                        ? Object.values(data.errors).flat().join('\n')
                        : (data?.message || 'No se pudo guardar el pedido.');

                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'error',
                            title: 'No se pudo guardar',
                            text: validationMessages,
                            confirmButtonText: 'Aceptar',
                            confirmButtonColor: '#dc3545',
                        });
                    } else {
                        alert(validationMessages);
                    }

                    return;
                }

                if (data?.printUrl) {
                    window.open(data.printUrl, '_blank', 'noopener,noreferrer');
                }

                if (window.Swal) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Pedido enviado a cocina',
                        text: data?.message || 'El pedido se guardó correctamente.',
                        confirmButtonText: 'Aceptar',
                        confirmButtonColor: '#16a34a',
                    });
                } else {
                    alert(data?.message || 'El pedido se guardó correctamente.');
                }

                window.location.reload();
            } catch (error) {
                if (window.Swal) {
                    await Swal.fire({
                        icon: 'error',
                        title: 'Error inesperado',
                        text: 'No se pudo guardar el pedido. Intenta nuevamente.',
                        confirmButtonText: 'Aceptar',
                        confirmButtonColor: '#dc3545',
                    });
                } else {
                    alert('No se pudo guardar el pedido. Intenta nuevamente.');
                }
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalLabel;
                }
            }
        });
    }

    if (customerSearch && customerSelect) {
        customerSearch.addEventListener('input', function () {
            renderCustomerOptions(customerSearch.value);
        });

        customerSelect.addEventListener('change', updateCustomerHelp);
        renderCustomerOptions('');
        updateCustomerHelp();
    }

    if (!rowsContainer || !addRowButton) return;
    const products = @json($productCatalog);
    const money = value => '$' + Number(value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const typeLabel = type => type === 'combo' ? 'Combo' : 'Producto';
    const productModal = productPickerModalElement && window.bootstrap ? new bootstrap.Modal(productPickerModalElement) : null;
    let activeProductRow = null;
    const productPreviewMarkup = product => {
        if (!product) {
            return '';
        }

        if (product.imageUrl) {
            return '<img src="' + product.imageUrl + '" alt="' + product.name.replace(/"/g, '&quot;') + '" style="width: 96px; height: 96px; object-fit: cover; border-radius: 16px; border: 1px solid #dbe3f1;">';
        }

        return '<div style="width: 96px; height: 96px; border-radius: 16px; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; color: #94a3b8;"><i class="fas fa-image"></i></div>';
    };
    const refresh = row => {
        const productSelect = row.querySelector('[data-order-product]');
        const productLabel = row.querySelector('[data-order-product-label]');
        const typeInput = row.querySelector('[data-order-type]');
        const priceLabel = row.querySelector('[data-order-price]');
        const previewContainer = row.querySelector('[data-order-preview]');
        const selectedProduct = products.find(product => String(product.id) === String(productSelect.value));
        typeInput.value = selectedProduct ? typeLabel(selectedProduct.type) : 'Producto';
        productLabel.textContent = selectedProduct ? selectedProduct.name + ' - ' + money(selectedProduct.price) : 'Selecciona un producto';
        priceLabel.textContent = selectedProduct ? 'Precio actual: ' + money(selectedProduct.price) + '.' : 'Selecciona un producto del menu o un combo disponible.';
        previewContainer.innerHTML = selectedProduct ? productPreviewMarkup(selectedProduct) : '';
    };
    const renderProductResults = searchTerm => {
        if (!productPickerResults) {
            return;
        }

        const normalizedSearch = (searchTerm || '').toString().trim().toLowerCase();
        const filteredProducts = normalizedSearch === ''
            ? products
            : products.filter(product => product.name.toLowerCase().includes(normalizedSearch));

        productPickerResults.innerHTML = filteredProducts.length > 0
            ? filteredProducts.map(product => '<div class="col-md-4 col-lg-3"><button type="button" class="btn btn-light border w-100 h-100 text-start p-3" data-pick-product="' + product.id + '"><div class="mb-3">' + (product.imageUrl ? '<img src="' + product.imageUrl + '" alt="' + product.name.replace(/"/g, '&quot;') + '" style="width: 100%; height: 180px; object-fit: cover; border-radius: 18px;">' : '<div style="width: 100%; height: 180px; border-radius: 18px; background: linear-gradient(135deg, #eff6ff, #dbeafe); display: flex; align-items: center; justify-content: center; color: #2563eb;"><i class="fas fa-image fa-2x"></i></div>') + '</div><div class="fw-bold">' + product.name + '</div><div class="table-note">' + typeLabel(product.type) + '</div><div class="mt-2 text-primary fw-semibold">' + money(product.price) + '</div></button></div>').join('')
            : '<div class="col-12"><div class="text-center text-muted py-4">No se encontraron productos para ese filtro.</div></div>';
    };
    const template = index => '<div class="component-row order-entry-row" data-order-row><div class="component-row-grid"><div class="component-field-large"><label class="form-label">Producto</label><input type="hidden" name="items[' + index + '][product_id]" value="" data-order-product><button type="button" class="btn btn-outline-primary w-100 text-start d-flex justify-content-between align-items-center" data-open-product-modal><span data-order-product-label>Selecciona un producto</span><i class="fas fa-search"></i></button></div><div><label class="form-label">Cantidad</label><input type="number" class="form-control" name="items[' + index + '][quantity]" min="1" value="1" required></div><div><label class="form-label">Tipo</label><input type="text" class="form-control" value="Producto" readonly data-order-type></div><div class="d-flex align-items-end"><button type="button" class="btn btn-outline-danger w-100" data-remove-order-row>Quitar</button></div></div><div class="form-help mt-2" data-order-price>Selecciona un producto del menu o un combo disponible.</div><div class="mt-3" data-order-preview></div></div>';
    addRowButton.addEventListener('click', () => rowsContainer.insertAdjacentHTML('beforeend', template(rowsContainer.querySelectorAll('[data-order-row]').length)));
    rowsContainer.addEventListener('click', event => {
        const pickerButton = event.target.closest('[data-open-product-modal]');

        if (pickerButton) {
            activeProductRow = pickerButton.closest('[data-order-row]');
            renderProductResults(productPickerSearch ? productPickerSearch.value : '');
            if (productPickerSearch) {
                productPickerSearch.focus();
            }
            if (productModal) {
                productModal.show();
            }
            return;
        }

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

    if (productPickerSearch) {
        productPickerSearch.addEventListener('input', function () {
            renderProductResults(productPickerSearch.value);
        });
    }

    if (productPickerResults) {
        productPickerResults.addEventListener('click', function (event) {
            const pickButton = event.target.closest('[data-pick-product]');

            if (!pickButton || !activeProductRow) {
                return;
            }

            activeProductRow.querySelector('[data-order-product]').value = pickButton.dataset.pickProduct;
            refresh(activeProductRow);

            if (productModal) {
                productModal.hide();
            }
        });
    }

    rowsContainer.querySelectorAll('[data-order-row]').forEach(refresh);
    renderProductResults('');
});
</script>
@endif
@endsection
