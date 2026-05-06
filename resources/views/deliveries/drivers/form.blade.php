@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
    @php
        $selectedVehicleType = strtolower((string) old('vehicle_type', $driver->vehicle_type));
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Domicilios / Domiciliarios</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Guarda los datos principales del domiciliario, su foto y la informacion del vehiculo para asignarlo facilmente a cada pedido.</p>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="card module-card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data">
                    @csrf
                    @if($driver->exists)
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Nombre</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $driver->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="document_number">Documento</label>
                            <input type="text" class="form-control" id="document_number" name="document_number" value="{{ old('document_number', $driver->document_number) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="phone">Telefono</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $driver->phone) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $driver->email) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="address">Direccion</label>
                            <input type="text" class="form-control" id="address" name="address" value="{{ old('address', $driver->address) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="vehicle_type">Tipo de vehiculo</label>
                            <select class="form-select" id="vehicle_type" name="vehicle_type" required>
                                <option value="">Selecciona un tipo</option>
                                @foreach($vehicleTypeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedVehicleType === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text" id="vehicleTypeHelp">Para carro y moto pedimos placa y modelo. Si es bicicleta, esos campos no se solicitan.</div>
                        </div>
                        <div class="col-md-4" id="vehiclePlateWrapper">
                            <label class="form-label" for="vehicle_plate">Placa</label>
                            <input type="text" class="form-control" id="vehicle_plate" name="vehicle_plate" value="{{ old('vehicle_plate', $driver->vehicle_plate) }}">
                        </div>
                        <div class="col-md-4" id="vehicleModelWrapper">
                            <label class="form-label" for="vehicle_model">Modelo o referencia</label>
                            <input type="text" class="form-control" id="vehicle_model" name="vehicle_model" value="{{ old('vehicle_model', $driver->vehicle_model) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="vehicle_color">Color</label>
                            <input type="text" class="form-control" id="vehicle_color" name="vehicle_color" value="{{ old('vehicle_color', $driver->vehicle_color) }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="photo">Foto</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div class="form-text">Opcional. Sube una foto de referencia del domiciliario.</div>

                            @if($driver->photo_url)
                                <div class="mt-3 d-flex align-items-center gap-3">
                                    <img src="{{ $driver->photo_url }}" alt="{{ $driver->name }}" style="width: 160px; height: 160px; object-fit: cover; border-radius: 18px; border: 1px solid #dbe3f1;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo" value="1">
                                        <label class="form-check-label" for="remove_photo">Quitar foto actual</label>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-3" id="photoPreviewWrapper" style="display: none;">
                                <p class="form-label mb-2">Vista previa</p>
                                <img id="photoPreview" alt="Vista previa del domiciliario" style="width: 160px; height: 160px; object-fit: cover; border-radius: 18px; border: 1px solid #dbe3f1;">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Notas</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes', $driver->notes) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $driver->is_active))>
                                <label class="form-check-label" for="is_active">
                                    Domiciliario activo
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions mt-4">
                        <a href="{{ route('deliveries.drivers.index') }}" class="btn btn-outline-secondary">Volver</a>
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
            const vehicleTypeInput = document.getElementById('vehicle_type');
            const vehiclePlateInput = document.getElementById('vehicle_plate');
            const vehicleModelInput = document.getElementById('vehicle_model');
            const vehiclePlateWrapper = document.getElementById('vehiclePlateWrapper');
            const vehicleModelWrapper = document.getElementById('vehicleModelWrapper');
            const vehicleTypeHelp = document.getElementById('vehicleTypeHelp');
            const photoInput = document.getElementById('photo');
            const photoPreview = document.getElementById('photoPreview');
            const photoPreviewWrapper = document.getElementById('photoPreviewWrapper');

            function syncVehicleFields() {
                if (! vehicleTypeInput || ! vehiclePlateInput || ! vehicleModelInput || ! vehiclePlateWrapper || ! vehicleModelWrapper) {
                    return;
                }

                const isBicycle = vehicleTypeInput.value === 'bicicleta';

                vehiclePlateInput.disabled = isBicycle;
                vehicleModelInput.disabled = isBicycle;
                vehiclePlateInput.required = ! isBicycle;
                vehicleModelInput.required = ! isBicycle;
                vehiclePlateWrapper.style.display = isBicycle ? 'none' : '';
                vehicleModelWrapper.style.display = isBicycle ? 'none' : '';

                if (vehicleTypeHelp) {
                    vehicleTypeHelp.textContent = isBicycle
                        ? 'Las bicicletas no requieren placa ni modelo.'
                        : 'Para carro y moto pedimos placa y modelo para identificar el vehiculo.';
                }
            }

            if (vehicleTypeInput) {
                vehicleTypeInput.addEventListener('change', syncVehicleFields);
                syncVehicleFields();
            }

            if (photoInput && photoPreview && photoPreviewWrapper) {
                photoInput.addEventListener('change', function () {
                    const file = photoInput.files && photoInput.files[0];

                    if (! file) {
                        photoPreviewWrapper.style.display = 'none';
                        photoPreview.removeAttribute('src');
                        return;
                    }

                    photoPreview.src = URL.createObjectURL(file);
                    photoPreviewWrapper.style.display = 'block';
                });
            }
        });
    </script>
@endsection
