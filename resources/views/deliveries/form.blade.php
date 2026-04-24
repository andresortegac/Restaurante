@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Pedidos / Domicilios</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Registra los datos del cliente, la dirección de entrega, el responsable y el estado actual del domicilio.</p>
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
                            <label class="form-label" for="delivery_number">Número</label>
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
                            <label class="form-label" for="assigned_user_id">Responsable</label>
                            <select class="form-select" id="assigned_user_id" name="assigned_user_id">
                                <option value="">Sin asignar</option>
                                @foreach($deliveryUsers as $deliveryUser)
                                    <option value="{{ $deliveryUser->id }}" @selected((string) old('assigned_user_id', $delivery->assigned_user_id) === (string) $deliveryUser->id)>{{ $deliveryUser->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_name">Nombre del cliente</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', $delivery->customer_name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_phone">Teléfono</label>
                            <input type="text" class="form-control" id="customer_phone" name="customer_phone" value="{{ old('customer_phone', $delivery->customer_phone) }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="delivery_address">Dirección de entrega</label>
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
                            <label class="form-label" for="delivery_fee">Costo envío</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="delivery_fee" name="delivery_fee" value="{{ old('delivery_fee', $delivery->delivery_fee ?? 0) }}" required>
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
