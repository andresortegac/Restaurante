@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Clientes</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Registra los datos necesarios para emitir factura electronica cuando el cliente la solicite.</p>
            </div>
        </section>

        @include('products.partials.form-errors', [
            'formErrorTitle' => 'No pudimos guardar el cliente.',
            'formErrorLead' => 'Revisa los campos marcados y vuelve a intentarlo.',
        ])

        <div class="card module-card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}">
                    @csrf
                    @if($customer->exists)
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Nombre o razon social</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $customer->name) }}" required data-required-message="Escribe el nombre o razón social del cliente.">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="document_number">Documento / NIT</label>
                            <input type="text" class="form-control" id="document_number" name="document_number" value="{{ old('document_number', $customer->document_number) }}" required data-required-message="Escribe el documento o NIT del cliente.">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Telefono</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}" required data-required-message="Escribe un teléfono de contacto.">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $customer->email) }}" required data-required-message="Escribe el correo electrónico del cliente." data-type-message="Escribe un correo electrónico válido, por ejemplo cliente@correo.com.">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="billing_address">Direccion de facturacion</label>
                            <input type="text" class="form-control" id="billing_address" name="billing_address" value="{{ old('billing_address', $customer->billing_address) }}" required data-required-message="Escribe la dirección de facturación del cliente.">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="identification_document_code">Tipo de documento</label>
                            <select class="form-select" id="identification_document_code" name="identification_document_code">
                                @foreach(['13' => 'Cedula de ciudadania', '31' => 'NIT', '22' => 'Cedula de extranjeria', '41' => 'Pasaporte'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('identification_document_code', $customer->identification_document_code ?: config('factus.default_identification_document_code', '13')) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="legal_organization_code">Tipo de persona</label>
                            <select class="form-select" id="legal_organization_code" name="legal_organization_code">
                                @foreach(['2' => 'Persona natural', '1' => 'Persona juridica'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('legal_organization_code', $customer->legal_organization_code ?: config('factus.default_legal_organization_code', '2')) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="tribute_code">Responsabilidad tributaria</label>
                            <select class="form-select" id="tribute_code" name="tribute_code">
                                @foreach(['ZZ' => 'No responsable de IVA', '01' => 'IVA'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('tribute_code', $customer->tribute_code ?: config('factus.default_tribute_code', 'ZZ')) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="municipality_code">Municipio DIAN</label>
                            <input type="text" class="form-control" id="municipality_code" name="municipality_code" value="{{ old('municipality_code', $customer->municipality_code ?: config('factus.default_municipality_code', '68001')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="trade_name">Nombre comercial</label>
                            <input type="text" class="form-control" id="trade_name" name="trade_name" value="{{ old('trade_name', $customer->trade_name) }}" placeholder="Opcional">
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $customer->is_active))>
                                <label class="form-check-label" for="is_active">Cliente activo</label>
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-required-message]').forEach(function(field) {
                field.addEventListener('invalid', function() {
                    if (field.validity.valueMissing) {
                        field.setCustomValidity(field.dataset.requiredMessage);
                        return;
                    }

                    if (field.validity.typeMismatch && field.dataset.typeMessage) {
                        field.setCustomValidity(field.dataset.typeMessage);
                        return;
                    }

                    field.setCustomValidity('');
                });

                field.addEventListener('input', function() {
                    field.setCustomValidity('');
                });
            });
        });
    </script>
@endpush
