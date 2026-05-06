@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Pedidos / Domicilios</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Registra el cliente, el domiciliario, el cobro y los datos necesarios para controlar la entrega.</p>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="card module-card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}">
                    @csrf
                    @if($delivery->exists)
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="delivery_number">Numero</label>
                            <input type="text" class="form-control" id="delivery_number" name="delivery_number" value="{{ old('delivery_number', $delivery->delivery_number) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="customer_id">Cliente vinculado</label>
                            <select class="form-select" id="customer_id" name="customer_id">
                                <option value="">Sin vincular</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected((string) old('customer_id', $delivery->customer_id) === (string) $customer->id)>{{ $customer->name }}{{ $customer->phone ? ' - ' . $customer->phone : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="delivery_driver_id">Responsable</label>
                            <select class="form-select" id="delivery_driver_id" name="delivery_driver_id">
                                <option value="">Sin asignar</option>
                                @foreach($deliveryDrivers as $deliveryDriver)
                                    <option value="{{ $deliveryDriver->id }}" @selected((string) old('delivery_driver_id', $delivery->delivery_driver_id) === (string) $deliveryDriver->id)>{{ $deliveryDriver->name }}{{ $deliveryDriver->is_active ? '' : ' (inactivo)' }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Listado de domiciliarios.</div>
                            @if(Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('delivery_drivers.create'))
                                <a href="{{ route('deliveries.drivers.create') }}" class="btn btn-link px-0">Registrar nuevo domiciliario</a>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_name">Nombre del cliente</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', $delivery->customer_name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_phone">Telefono</label>
                            <input type="text" class="form-control" id="customer_phone" name="customer_phone" value="{{ old('customer_phone', $delivery->customer_phone) }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="delivery_address">Direccion de entrega</label>
                            <input type="text" class="form-control" id="delivery_address" name="delivery_address" value="{{ old('delivery_address', $delivery->delivery_address) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="reference">Referencia</label>
                            <input type="text" class="form-control" id="reference" name="reference" value="{{ old('reference', $delivery->reference) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="order_total">Total pedido</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="order_total" name="order_total" value="{{ old('order_total', $delivery->order_total ?? 0) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="delivery_fee">Costo envio</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="delivery_fee" name="delivery_fee" value="{{ old('delivery_fee', $delivery->delivery_fee ?? 0) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="customer_payment_amount">Paga con</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="customer_payment_amount" name="customer_payment_amount" value="{{ old('customer_payment_amount', $delivery->customer_payment_amount ?? 0) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="change_required_preview">Cambio a devolver</label>
                            <input type="text" class="form-control" id="change_required_preview" value="{{ number_format((float) old('change_required', $delivery->change_required ?? 0), 2, '.', '') }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="status">Estado</label>
                            <select class="form-select" id="status" name="status" required>
                                @foreach(['pending' => 'Pendiente', 'assigned' => 'Asignado', 'in_transit' => 'En camino', 'delivered' => 'Entregado', 'cancelled' => 'Cancelado'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $delivery->status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="scheduled_at">Programado para</label>
                            <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at', optional($delivery->scheduled_at)->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Notas</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes', $delivery->notes) }}</textarea>
                        </div>
                        @if($delivery->delivery_proof_image_url)
                            <div class="col-12">
                                <label class="form-label d-block">Evidencia de entrega</label>
                                <a href="{{ $delivery->delivery_proof_image_url }}" target="_blank" rel="noopener noreferrer">
                                    <img src="{{ $delivery->delivery_proof_image_url }}" alt="Evidencia del domicilio" style="width: 180px; height: 180px; object-fit: cover; border-radius: 18px; border: 1px solid #dbe3f1;">
                                </a>
                            </div>
                        @endif
                    </div>

                    <div class="form-actions mt-4">
                        <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary">Volver</a>
                        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const orderTotalInput = document.getElementById('order_total');
            const deliveryFeeInput = document.getElementById('delivery_fee');
            const customerPaymentInput = document.getElementById('customer_payment_amount');
            const changePreviewInput = document.getElementById('change_required_preview');

            const updateChangePreview = () => {
                const orderTotal = parseFloat(orderTotalInput?.value || '0');
                const deliveryFee = parseFloat(deliveryFeeInput?.value || '0');
                const customerPayment = parseFloat(customerPaymentInput?.value || '0');
                const changeRequired = Math.max(customerPayment - (orderTotal + deliveryFee), 0);

                if (changePreviewInput) {
                    changePreviewInput.value = changeRequired.toFixed(2);
                }
            };

            [orderTotalInput, deliveryFeeInput, customerPaymentInput].forEach(function (input) {
                if (input) {
                    input.addEventListener('input', updateChangePreview);
                }
            });

            updateChangePreview();
        });
    </script>
@endsection
