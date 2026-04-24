@extends('layouts.app')

@section('title', 'Usuarios - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Administracion / Usuarios</span>
                <h1>Usuarios del sistema</h1>
                <p>Gestiona las cuentas internas del restaurante y define que roles tiene asignado cada usuario.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['total'] }} usuarios</span>
                <span class="summary-chip">{{ $summary['admins'] }} admins</span>
                <span class="summary-chip">{{ $summary['nonAdmins'] }} no admins</span>
            </div>
        </section>

        <div class="module-toolbar">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-end flex-grow-1">
                <div class="col-md-7">
                    <label class="form-label" for="search">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nombre o correo">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="role_id">Rol</label>
                    <select class="form-select" id="role_id" name="role_id">
                        <option value="">Todos</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) ($filters['role_id'] ?? '') === (string) $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                </div>
            </form>

            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nuevo usuario
            </a>
        </div>

        <div class="card module-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Roles</th>
                                <th>Creado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $userModel)
                                <tr>
                                    <td>
                                        <strong>{{ $userModel->name }}</strong>
                                        <div class="table-note">{{ $userModel->email }}</div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            @forelse($userModel->roles as $role)
                                                <span class="badge rounded-pill {{ $role->name === 'Admin' ? 'bg-primary' : 'bg-secondary' }}">{{ $role->name }}</span>
                                            @empty
                                                <span class="text-muted">Sin roles</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>{{ $userModel->created_at?->format('d/m/Y') ?? 'Sin fecha' }}</td>
                                    <td>
                                        <div class="table-actions justify-content-end">
                                            <a href="{{ route('admin.users.edit', $userModel) }}" class="btn btn-outline-primary btn-sm">Editar</a>
                                            <form method="POST" action="{{ route('admin.users.destroy', $userModel) }}" onsubmit="return confirm('Deseas eliminar este usuario?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Todavia no hay usuarios registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $users->links() }}
        </div>
    </div>
@endsection
