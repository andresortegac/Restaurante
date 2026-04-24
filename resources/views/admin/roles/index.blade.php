@extends('layouts.app')

@section('title', 'Roles - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Administracion / Roles</span>
                <h1>Roles del sistema</h1>
                <p>Define perfiles operativos y controla que permisos hereda cada grupo de trabajo.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['roles'] }} roles</span>
                <span class="summary-chip">{{ $summary['permissions'] }} permisos</span>
                <span class="summary-chip">{{ $summary['admins'] }} usuarios admin</span>
            </div>
        </section>

        <div class="module-toolbar">
            <div>
                <h5 class="mb-1">Configuracion de roles</h5>
                <p class="table-note mb-0">Puedes crear nuevos roles, editar su descripcion y ajustar sus permisos.</p>
            </div>
            <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nuevo rol
            </a>
        </div>

        <div class="card module-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Rol</th>
                                <th>Permisos</th>
                                <th>Usuarios</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($roles as $role)
                                <tr>
                                    <td>
                                        <strong>{{ $role->name }}</strong>
                                        <div class="table-note">{{ $role->description ?: 'Sin descripcion' }}</div>
                                    </td>
                                    <td>{{ $role->permissions_count }}</td>
                                    <td>{{ $role->users_count }}</td>
                                    <td>
                                        <div class="table-actions justify-content-end">
                                            <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-outline-primary btn-sm">Editar</a>
                                            @if($role->name !== 'Admin')
                                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Deseas eliminar este rol?');">
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
                                    <td colspan="4" class="text-center py-4 text-muted">No hay roles registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
