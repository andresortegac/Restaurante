@extends('layouts.app')

@section('title', 'Configuración Factus - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Facturación / Factus</span>
                <h1>Configuración de facturación electrónica</h1>
                <p>Define credenciales, entorno, rango de numeración y valores por defecto necesarios para emitir facturas electrónicas con Factus.</p>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="card module-card">
            <div class="card-body">
                <form method="POST" action="{{ route('electronic-invoices.settings.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label" for="environment">Entorno</label>
                            <select class="form-select" id="environment" name="environment" required>
                                <option value="sandbox" @selected(old('environment', $settings->environment) === 'sandbox')>Sandbox</option>
                                <option value="production" @selected(old('environment', $settings->environment) === 'production')>Producción</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="document_code">Documento</label>
                            <input type="text" class="form-control" id="document_code" name="document_code" value="{{ old('document_code', $settings->document_code) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="operation_type">Tipo operación</label>
                            <input type="text" class="form-control" id="operation_type" name="operation_type" value="{{ old('operation_type', $settings->operation_type) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="numbering_range_id">Rango numeración</label>
                            <select class="form-select" id="numbering_range_id" name="numbering_range_id">
                                <option value="">Seleccionar</option>
                                @foreach($numberingRanges as $range)
                                    <option value="{{ $range['id'] }}" @selected((string) old('numbering_range_id', $settings->numbering_range_id) === (string) $range['id'])>
                                        {{ ($range['prefix'] ?? 'SINPREF') . ' | Resolución ' . ($range['resolution_number'] ?? 'N/A') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="client_id">Client ID</label>
                            <input type="text" class="form-control" id="client_id" name="client_id" value="{{ old('client_id', $settings->client_id) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="username">Usuario API</label>
                            <input type="text" class="form-control" id="username" name="username" value="{{ old('username', $settings->username) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="client_secret">Client Secret</label>
                            <input type="password" class="form-control" id="client_secret" name="client_secret" placeholder="{{ $settings->client_secret ? 'Conservado' : '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password">Contraseña API</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="{{ $settings->password ? 'Conservada' : '' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="default_identification_document_code">Doc. identificación</label>
                            <input type="text" class="form-control" id="default_identification_document_code" name="default_identification_document_code" value="{{ old('default_identification_document_code', $settings->default_identification_document_code) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="default_legal_organization_code">Org. legal</label>
                            <input type="text" class="form-control" id="default_legal_organization_code" name="default_legal_organization_code" value="{{ old('default_legal_organization_code', $settings->default_legal_organization_code) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="default_tribute_code">Tributo</label>
                            <input type="text" class="form-control" id="default_tribute_code" name="default_tribute_code" value="{{ old('default_tribute_code', $settings->default_tribute_code) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="default_municipality_code">Municipio</label>
                            <input type="text" class="form-control" id="default_municipality_code" name="default_municipality_code" value="{{ old('default_municipality_code', $settings->default_municipality_code) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="default_unit_measure_code">Unidad medida</label>
                            <input type="text" class="form-control" id="default_unit_measure_code" name="default_unit_measure_code" value="{{ old('default_unit_measure_code', $settings->default_unit_measure_code) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="default_standard_code">Código estándar</label>
                            <input type="text" class="form-control" id="default_standard_code" name="default_standard_code" value="{{ old('default_standard_code', $settings->default_standard_code) }}" required>
                        </div>
                        <div class="col-12 d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" value="1" @checked(old('is_enabled', $settings->is_enabled))>
                                <label class="form-check-label" for="is_enabled">Módulo habilitado</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="send_email" name="send_email" value="1" @checked(old('send_email', $settings->send_email))>
                                <label class="form-check-label" for="send_email">Enviar correo desde Factus</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions mt-4">
                        <a href="{{ route('electronic-invoices.index') }}" class="btn btn-outline-secondary">Volver</a>
                        <button type="submit" class="btn btn-primary">Guardar configuración</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
