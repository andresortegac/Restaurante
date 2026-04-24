@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Administracion / Usuarios</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Completa los datos de acceso del usuario y selecciona uno o varios roles para definir su alcance en el sistema.</p>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="card module-card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}">
                    @csrf
                    @if($userModel->exists)
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Nombre</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $userModel->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Correo</label>
                            <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $userModel->email) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password">Contrasena {{ $userModel->exists ? '(opcional)' : '' }}</label>
                            <input type="password" class="form-control" id="password" name="password" {{ $userModel->exists ? '' : 'required' }}>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password_confirmation">Confirmar contrasena</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" {{ $userModel->exists ? '' : 'required' }}>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Roles asignados</label>
                            <div class="row g-3">
                                @foreach($roles as $role)
                                    <div class="col-md-4">
                                        <label class="border rounded-3 p-3 w-100 h-100">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="roles[]"
                                                    value="{{ $role->id }}"
                                                    id="role_{{ $role->id }}"
                                                    @checked(in_array($role->id, collect($selectedRoles)->map(fn ($roleId) => (int) $roleId)->all(), true))
                                                >
                                                <span class="form-check-label fw-semibold">{{ $role->name }}</span>
                                            </div>
                                            <div class="table-note mt-2">{{ $role->description ?: 'Sin descripcion' }}</div>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="form-actions mt-4">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Volver</a>
                        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
