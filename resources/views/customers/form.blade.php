@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Clientes / CRUD</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Guarda los datos basicos del cliente para reutilizarlos al tomar pedidos y en ventas futuras.</p>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="card module-card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}">
                    @csrf
                    @if($customer->exists)
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Nombre</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $customer->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="document_number">Documento</label>
                            <input type="text" class="form-control" id="document_number" name="document_number" value="{{ old('document_number', $customer->document_number) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="billing_identification">Identificación para factura electrónica</label>
                            <input type="text" class="form-control" id="billing_identification" name="billing_identification" value="{{ old('billing_identification', $customer->billing_identification) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Telefono</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $customer->email) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="billing_address">Dirección fiscal</label>
                            <input type="text" class="form-control" id="billing_address" name="billing_address" value="{{ old('billing_address', $customer->billing_address) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="trade_name">Nombre comercial</label>
                            <input type="text" class="form-control" id="trade_name" name="trade_name" value="{{ old('trade_name', $customer->trade_name) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="identification_document_code">Tipo documento DIAN</label>
                            <input type="text" class="form-control" id="identification_document_code" name="identification_document_code" value="{{ old('identification_document_code', $customer->identification_document_code) }}" placeholder="13, 31...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="legal_organization_code">Organización legal</label>
                            <input type="text" class="form-control" id="legal_organization_code" name="legal_organization_code" value="{{ old('legal_organization_code', $customer->legal_organization_code) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="tribute_code">Tributo</label>
                            <input type="text" class="form-control" id="tribute_code" name="tribute_code" value="{{ old('tribute_code', $customer->tribute_code) }}" placeholder="ZZ">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="municipality_code">Código municipio</label>
                            <input type="text" class="form-control" id="municipality_code" name="municipality_code" value="{{ old('municipality_code', $customer->municipality_code) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Notas</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes', $customer->notes) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $customer->is_active))>
                                <label class="form-check-label" for="is_active">
                                    Cliente activo
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions mt-4">
                        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Volver</a>
                        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
