@extends('layouts.app')

@section('title', 'Permisos - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Administracion / Permisos</span>
                <h1>Permisos disponibles</h1>
                <p>Consulta el catalogo completo de permisos y revisa en que roles esta asignado cada uno.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['permissions'] }} permisos</span>
                <span class="summary-chip">{{ $summary['groups'] }} grupos</span>
                <span class="summary-chip">{{ $summary['assigned'] }} asignados</span>
            </div>
        </section>

        <div class="card module-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Permiso</th>
                                <th>Descripcion</th>
                                <th>Roles asignados</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($permissions as $permission)
                                <tr>
                                    <td>
                                        <strong>{{ $permission->name }}</strong>
                                    </td>
                                    <td>{{ $permission->description ?: 'Sin descripcion' }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            @forelse($permission->roles as $role)
                                                <span class="badge rounded-pill {{ $role->name === 'Admin' ? 'bg-primary' : 'bg-secondary' }}">{{ $role->name }}</span>
                                            @empty
                                                <span class="text-muted">Sin asignar</span>
                                            @endforelse
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">No hay permisos registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
