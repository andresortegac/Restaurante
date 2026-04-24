@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Administracion / Roles</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Asigna una descripcion clara al rol y marca los permisos que debera heredar.</p>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="card module-card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}">
                    @csrf
                    @if($roleModel->exists)
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Nombre del rol</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $roleModel->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="description">Descripcion</label>
                            <input type="text" class="form-control" id="description" name="description" value="{{ old('description', $roleModel->description) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Permisos del rol</label>
                            <div class="row g-3">
                                @foreach($permissionGroups as $groupName => $permissions)
                                    <div class="col-lg-4 col-md-6">
                                        <div class="border rounded-3 p-3 h-100">
                                            <h6 class="mb-3">{{ $groupName }}</h6>
                                            <div class="d-flex flex-column gap-2">
                                                @foreach($permissions as $permission)
                                                    <label class="form-check">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            name="permissions[]"
                                                            value="{{ $permission->id }}"
                                                            @checked(in_array($permission->id, collect($selectedPermissions)->map(fn ($permissionId) => (int) $permissionId)->all(), true))
                                                        >
                                                        <span class="form-check-label">{{ $permission->name }}</span>
                                                        <div class="table-note">{{ $permission->description ?: 'Sin descripcion' }}</div>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="form-actions mt-4">
                        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Volver</a>
                        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
