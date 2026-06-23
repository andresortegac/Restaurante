@extends('layouts.app')

@section('title', $pageTitle . ' - RestaurantePOS')

@section('content')
    @php
        $hasOpenOrder = $restaurantTable->exists && $restaurantTable->openOrder()->exists();
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Gestion de Mesas</span>
                <h1>{{ $pageTitle }}</h1>
            </div>
            <div class="summary-group">
                <a href="{{ route('tables.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a mesas
                </a>
            </div>
        </section>

        @include('products.partials.form-errors')

        @if($hasOpenOrder)
            <div class="alert alert-warning module-alert" role="alert">
                Esta mesa tiene un pedido abierto. Si cambias el estado manualmente, el sistema mantendra la mesa como ocupada hasta cerrar la cuenta.
            </div>
        @endif

        <div class="card module-card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}">
                    @csrf
                    @if($restaurantTable->exists)
                        @method('PUT')
                    @endif

                    @include('tables.partials.form-fields', ['restaurantTable' => $restaurantTable])

                    <div class="form-actions">
                        <a href="{{ route('tables.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
