@extends('layouts.app')

@section('title', 'Domiciliarios - RestaurantePOS')

@section('content')
    @php
        $canCreateDriver = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('delivery_drivers.create');
        $canEditDriver = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('delivery_drivers.edit');
        $canDeleteDriver = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('delivery_drivers.delete');
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Domicilios / Domiciliarios</span>
                <h1>Equipo de domiciliarios</h1>
                <p>Administra la base de repartidores, su foto de referencia y los datos del vehiculo que usan para las entregas.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['total'] }} registrados</span>
                <span class="summary-chip">{{ $summary['active'] }} activos</span>
                <span class="summary-chip">{{ $summary['inactive'] }} inactivos</span>
            </div>
        </section>

        <div class="module-toolbar">
            <form method="GET" action="{{ route('deliveries.drivers.index') }}" class="row g-2 align-items-end flex-grow-1">
                <div class="col-md-6">
                    <label class="form-label" for="search">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nombre, documento, telefono o placa">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Activos</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                    <a href="{{ route('deliveries.drivers.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
                </div>
            </form>

            @if($canCreateDriver)
                <a href="{{ route('deliveries.drivers.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo domiciliario
                </a>
            @endif
        </div>

        <div class="card module-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Domiciliario</th>
                                <th>Contacto</th>
                                <th>Vehiculo</th>
                                <th>Historial</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($drivers as $driver)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            @if($driver->photo_url)
                                                <img src="{{ $driver->photo_url }}" alt="{{ $driver->name }}" style="width: 56px; height: 56px; object-fit: cover; border-radius: 16px; border: 1px solid #dbe3f1;">
                                            @else
                                                <div class="d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; border-radius: 16px; border: 1px dashed #dbe3f1; color: #6b7280;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            @endif
                                            <div>
                                                <strong>{{ $driver->name }}</strong>
                                                <div class="table-note">{{ $driver->document_number ?: 'Sin documento' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>{{ $driver->phone }}</div>
                                        <div class="table-note">{{ $driver->email ?: 'Sin email' }}</div>
                                        <div class="table-note">{{ $driver->address ?: 'Sin direccion' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $driver->vehicle_type_label }}</div>
                                        <div class="table-note">{{ $driver->vehicle_plate ?: 'Sin placa' }}</div>
                                        <div class="table-note">{{ collect([$driver->vehicle_model, $driver->vehicle_color])->filter()->join(' / ') ?: 'Sin detalle adicional' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $driver->deliveries_count }} domicilios</div>
                                        <div class="table-note">{{ $driver->created_at?->format('d/m/Y') ?: '-' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill {{ $driver->is_active ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $driver->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions justify-content-end">
                                            @if($canEditDriver)
                                                <a href="{{ route('deliveries.drivers.edit', $driver) }}" class="btn btn-outline-primary btn-sm">Editar</a>
                                            @endif
                                            @if($canDeleteDriver)
                                                <form method="POST" action="{{ route('deliveries.drivers.destroy', $driver) }}" onsubmit="return confirm('Deseas eliminar este domiciliario?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Todavia no hay domiciliarios registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $drivers->links() }}
        </div>
    </div>
@endsection
