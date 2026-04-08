@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Productos / RF-22</span>
                <h1>{{ $pageTitle }}</h1>
                <p>Configura la tasa, el modo de aplicacion y si sera la regla predeterminada para nuevos productos.</p>
            </div>
            <div class="summary-group">
                <a href="{{ route('products.taxes.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a impuestos
                </a>
            </div>
        </section>

        @include('products.partials.form-errors')

        <div class="card module-card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}">
                    @csrf
                    @if($taxRate->exists)
                        @method('PUT')
                    @endif

                    <div class="form-grid">
                        <div>
                            <label class="form-label" for="name">Nombre</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $taxRate->name) }}" required>
                        </div>

                        <div>
                            <label class="form-label" for="code">Codigo</label>
                            <input type="text" class="form-control" id="code" name="code" value="{{ old('code', $taxRate->code) }}">
                        </div>

                        <div>
                            <label class="form-label" for="rate">Tasa (%)</label>
                            <input type="number" class="form-control" id="rate" name="rate" min="0" max="100" step="0.01" value="{{ old('rate', $taxRate->rate) }}" required>
                        </div>

                        <div class="full-width">
                            <label class="form-label" for="description">Descripcion</label>
                            <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $taxRate->description) }}</textarea>
                        </div>

                        <div class="checkbox-grid full-width">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_inclusive" name="is_inclusive" value="1" @checked(old('is_inclusive', $taxRate->is_inclusive))>
                                <label class="form-check-label" for="is_inclusive">Incluido en el precio final</label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" @checked(old('is_default', $taxRate->is_default))>
                                <label class="form-check-label" for="is_default">Usar como impuesto predeterminado</label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $taxRate->exists ? $taxRate->is_active : true))>
                                <label class="form-check-label" for="is_active">Impuesto activo</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('products.taxes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
