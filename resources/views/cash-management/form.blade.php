@extends('layouts.app')

@section('title', $pageTitle . ' - Gestion de Caja')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">POS / Gestion de Caja</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Configura el punto fisico de cobro. Esta creacion se hace una sola vez; lo diario es abrir y cerrar sesiones sobre esta caja.</p>
            </div>
            <div class="summary-group">
                <a href="{{ route('cash-management.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a cajas
                </a>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card module-card">
                    <div class="card-body">
                        <form method="POST" action="{{ $formAction }}">
                            @csrf
                            @if($box->exists)
                                @method('PUT')
                            @endif

                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label class="form-label" for="name">Nombre de la caja</label>
                                    <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $box->name) }}" placeholder="Ej: Caja principal, Caja barra, Caja domicilios" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="code">Codigo interno</label>
                                    <input type="text" class="form-control" id="code" name="code" value="{{ old('code', $box->code) }}" placeholder="Ej: BOX-001" required>
                                </div>
                            </div>

                            <div class="form-actions mt-4">
                                <a href="{{ route('cash-management.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-lightbulb"></i> Recomendaciones</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            <div class="module-list-item">
                                <div>
                                    <strong>Una caja por punto fisico</strong>
                                    <div class="table-note">Crea una caja diferente para cada lugar real de cobro del restaurante.</div>
                                </div>
                            </div>
                            <div class="module-list-item">
                                <div>
                                    <strong>Codigo corto y unico</strong>
                                    <div class="table-note">Usa identificadores faciles de recordar, por ejemplo BOX-PRINCIPAL o BOX-BARRA.</div>
                                </div>
                            </div>
                            <div class="module-list-item">
                                <div>
                                    <strong>Sin base fija aqui</strong>
                                    <div class="table-note">La base inicial no se define al crear la caja, sino cada vez que el cajero abre una sesion diaria.</div>
                                </div>
                            </div>
                            <div class="module-list-item">
                                <div>
                                    <strong>Cierres por sesion</strong>
                                    <div class="table-note">Cada apertura y cierre genera historial y conciliacion independiente.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
