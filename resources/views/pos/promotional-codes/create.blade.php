@extends('layouts.app')

@section('title', 'Codigos Promocionales - RestaurantePOS')

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Codigos promocionales</h1>
                <p class="text-muted mb-0">Crea cupones para aplicar descuentos en el punto de venta.</p>
            </div>
            <a href="{{ route('pos.index') }}" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Volver al POS
            </a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <h2 class="h6 mb-2">No pudimos guardar el codigo promocional.</h2>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-xl-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h2 class="h5 mb-0">Nuevo codigo promocional</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('pos.promo-codes.store') }}">
                            @csrf

                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="discount_name" class="form-label">Nombre del descuento</label>
                                    <input
                                        type="text"
                                        class="form-control @error('discount_name') is-invalid @enderror"
                                        id="discount_name"
                                        name="discount_name"
                                        value="{{ old('discount_name') }}"
                                        placeholder="Ej: Promo almuerzo ejecutivo"
                                        required
                                    >
                                </div>

                                <div class="col-md-4">
                                    <label for="code" class="form-label">Codigo</label>
                                    <input
                                        type="text"
                                        class="form-control @error('code') is-invalid @enderror"
                                        id="code"
                                        name="code"
                                        value="{{ old('code') }}"
                                        placeholder="ALMUERZO10"
                                        required
                                    >
                                </div>

                                <div class="col-12">
                                    <label for="discount_description" class="form-label">Descripcion</label>
                                    <textarea
                                        class="form-control @error('discount_description') is-invalid @enderror"
                                        id="discount_description"
                                        name="discount_description"
                                        rows="3"
                                        placeholder="Detalle breve de la promocion"
                                    >{{ old('discount_description') }}</textarea>
                                </div>

                                <div class="col-md-4">
                                    <label for="discount_type" class="form-label">Tipo de descuento</label>
                                    <select
                                        class="form-select @error('discount_type') is-invalid @enderror"
                                        id="discount_type"
                                        name="discount_type"
                                        required
                                    >
                                        <option value="percentage" @selected(old('discount_type', 'percentage') === 'percentage')>Porcentaje</option>
                                        <option value="fixed" @selected(old('discount_type') === 'fixed')>Monto fijo</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label for="discount_value" class="form-label">Valor</label>
                                    <input
                                        type="number"
                                        class="form-control @error('discount_value') is-invalid @enderror"
                                        id="discount_value"
                                        name="discount_value"
                                        value="{{ old('discount_value') }}"
                                        min="0.01"
                                        step="0.01"
                                        placeholder="10"
                                        required
                                    >
                                </div>

                                <div class="col-md-4">
                                    <label for="usage_limit" class="form-label">Limite de usos</label>
                                    <input
                                        type="number"
                                        class="form-control @error('usage_limit') is-invalid @enderror"
                                        id="usage_limit"
                                        name="usage_limit"
                                        value="{{ old('usage_limit') }}"
                                        min="1"
                                        placeholder="Opcional"
                                    >
                                </div>

                                <div class="col-md-4">
                                    <label for="min_purchase_amount" class="form-label">Compra minima</label>
                                    <input
                                        type="number"
                                        class="form-control @error('min_purchase_amount') is-invalid @enderror"
                                        id="min_purchase_amount"
                                        name="min_purchase_amount"
                                        value="{{ old('min_purchase_amount') }}"
                                        min="0"
                                        step="0.01"
                                        placeholder="0.00"
                                    >
                                </div>

                                <div class="col-md-4">
                                    <label for="discount_starts_at" class="form-label">Inicio</label>
                                    <input
                                        type="datetime-local"
                                        class="form-control @error('discount_starts_at') is-invalid @enderror"
                                        id="discount_starts_at"
                                        name="discount_starts_at"
                                        value="{{ old('discount_starts_at') }}"
                                    >
                                </div>

                                <div class="col-md-4">
                                    <label for="discount_ends_at" class="form-label">Fin del descuento</label>
                                    <input
                                        type="datetime-local"
                                        class="form-control @error('discount_ends_at') is-invalid @enderror"
                                        id="discount_ends_at"
                                        name="discount_ends_at"
                                        value="{{ old('discount_ends_at') }}"
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label for="expires_at" class="form-label">Vence el codigo</label>
                                    <input
                                        type="datetime-local"
                                        class="form-control @error('expires_at') is-invalid @enderror"
                                        id="expires_at"
                                        name="expires_at"
                                        value="{{ old('expires_at') }}"
                                    >
                                </div>

                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check form-switch mb-2">
                                        <input type="hidden" name="active" value="0">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            role="switch"
                                            id="active"
                                            name="active"
                                            value="1"
                                            @checked((string) old('active', '1') === '1')
                                        >
                                        <label class="form-check-label" for="active">Dejar activo al crear</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar codigo
                                </button>
                                <a href="{{ route('pos.index') }}" class="btn btn-light border">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h2 class="h5 mb-0">Codigos recientes</h2>
                    </div>
                    <div class="card-body">
                        @if($coupons->isEmpty())
                            <p class="text-muted mb-0">Aun no hay codigos promocionales registrados.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Codigo</th>
                                            <th>Descuento</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($coupons as $coupon)
                                            @php
                                                $isExpired = $coupon->expires_at && $coupon->expires_at->isPast();
                                                $statusClass = !$coupon->active ? 'secondary' : ($isExpired ? 'warning text-dark' : 'success');
                                                $statusLabel = !$coupon->active ? 'Inactivo' : ($isExpired ? 'Vencido' : 'Activo');
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $coupon->code }}</div>
                                                    <small class="text-muted">
                                                        {{ $coupon->usage_count }} / {{ $coupon->usage_limit ?? 'Sin limite' }} usos
                                                    </small>
                                                </td>
                                                <td>
                                                    <div>{{ $coupon->discount->name }}</div>
                                                    <small class="text-muted">
                                                        @if($coupon->discount->type === 'percentage')
                                                            {{ number_format($coupon->discount->value, 2) }}%
                                                        @else
                                                            ${{ number_format($coupon->discount->value, 2) }}
                                                        @endif
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
                                                    <div class="small text-muted mt-1">
                                                        {{ $coupon->expires_at ? $coupon->expires_at->format('d/m/Y H:i') : 'Sin vencimiento' }}
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
