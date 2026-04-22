@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Reservas / CRUD</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Registra la fecha, el tamano del grupo, la mesa sugerida y las observaciones clave para coordinar la llegada del cliente.</p>
            </div>
            <div class="summary-group">
                <a href="{{ route('reservations.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a reservas
                </a>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="card module-card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}">
                    @csrf
                    @if($reservation->exists)
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="customer_id">Cliente existente</label>
                            <select class="form-select" id="customer_id" name="customer_id">
                                <option value="">Seleccionar cliente...</option>
                                @foreach($customers as $customer)
                                    <option
                                        value="{{ $customer->id }}"
                                        data-name="{{ $customer->name }}"
                                        data-phone="{{ $customer->phone }}"
                                        data-email="{{ $customer->email }}"
                                        @selected((string) old('customer_id', $reservation->customer_id) === (string) $customer->id)
                                    >
                                        {{ $customer->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-help">Opcional. Si lo eliges, se autocompletan los datos de contacto.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="restaurant_table_id">Mesa sugerida</label>
                            <select class="form-select" id="restaurant_table_id" name="restaurant_table_id">
                                <option value="">Asignar luego</option>
                                @foreach($tables as $table)
                                    <option value="{{ $table->id }}" @selected((string) old('restaurant_table_id', $reservation->restaurant_table_id) === (string) $table->id)>
                                        {{ $table->name }} - {{ $table->area ?: 'Salon principal' }} - Cap. {{ $table->capacity }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="customer_name">Nombre del cliente</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', $reservation->customer_name) }}" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="customer_phone">Telefono</label>
                            <input type="text" class="form-control" id="customer_phone" name="customer_phone" value="{{ old('customer_phone', $reservation->customer_phone) }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="customer_email">Email</label>
                            <input type="email" class="form-control" id="customer_email" name="customer_email" value="{{ old('customer_email', $reservation->customer_email) }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="reservation_at">Fecha y hora</label>
                            <input type="datetime-local" class="form-control" id="reservation_at" name="reservation_at" value="{{ old('reservation_at', $reservation->reservation_at ? $reservation->reservation_at->format('Y-m-d\TH:i') : '') }}" required>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label" for="party_size">Personas</label>
                            <input type="number" class="form-control" id="party_size" name="party_size" min="1" max="30" value="{{ old('party_size', $reservation->party_size) }}" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="status">Estado</label>
                            <select class="form-select" id="status" name="status" required>
                                @foreach($statusLabels as $status => $label)
                                    <option value="{{ $status }}" @selected(old('status', $reservation->status) === $status)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="source">Canal</label>
                            <input type="text" class="form-control" id="source" name="source" value="{{ old('source', $reservation->source) }}" placeholder="Telefono, WhatsApp, web...">
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="special_requests">Solicitudes especiales</label>
                            <textarea class="form-control" id="special_requests" name="special_requests" rows="3" placeholder="Silla para nino, aniversario, zona tranquila...">{{ old('special_requests', $reservation->special_requests) }}</textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="notes">Notas internas</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Detalles operativos para el equipo.">{{ old('notes', $reservation->notes) }}</textarea>
                        </div>
                    </div>

                    <div class="form-actions mt-4">
                        <a href="{{ route('reservations.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const customerSelect = document.getElementById('customer_id');
            const nameInput = document.getElementById('customer_name');
            const phoneInput = document.getElementById('customer_phone');
            const emailInput = document.getElementById('customer_email');

            if (!customerSelect) {
                return;
            }

            customerSelect.addEventListener('change', function () {
                const selected = customerSelect.options[customerSelect.selectedIndex];

                if (!selected || !selected.value) {
                    return;
                }

                nameInput.value = selected.dataset.name || nameInput.value;
                phoneInput.value = selected.dataset.phone || phoneInput.value;
                emailInput.value = selected.dataset.email || emailInput.value;
            });
        });
    </script>
@endsection
