@extends('layouts.app')

@section('title', 'Gestion de Caja - RestaurantePOS')

@section('content')
    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">POS / Gestion de Caja</span>
                <h1>Cajas y sesiones activas</h1>
                <p>Controla aperturas, cierres y el estado operativo de cada caja del restaurante.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['boxes'] }} cajas</span>
                <span class="summary-chip">{{ $summary['openSessions'] }} abiertas</span>
                <span class="summary-chip">${{ number_format($summary['todayIncome'], 2) }} ingresos hoy</span>
                <span class="summary-chip">${{ number_format($summary['todayExpense'], 2) }} egresos hoy</span>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-wallet"></i> Cajas registradas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach($boxes as $box)
                                <div class="col-md-6">
                                    <article class="cash-box-card h-100">
                                        <div class="cash-box-header">
                                            <div>
                                                <h5 class="mb-1">{{ $box->name }}</h5>
                                                <div class="table-note">{{ $box->code }}</div>
                                            </div>
                                            <span class="badge rounded-pill cash-box-status {{ $box->activeSession ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $box->activeSession ? 'Abierta' : 'Cerrada' }}
                                            </span>
                                        </div>
                                        <div class="cash-box-meta">
                                            <div class="table-note">
                                                Responsable: {{ $box->activeSession?->user?->name ?? $box->user?->name ?? 'Sin asignar' }}
                                            </div>
                                            <div class="table-note">
                                                Ultima apertura: {{ $box->opened_at ? $box->opened_at->format('d/m/Y H:i') : 'Sin registros' }}
                                            </div>
                                        </div>
                                        <div class="cash-box-actions">
                                            <a href="{{ route('cash-management.show', $box) }}" class="btn cash-box-manage-btn">
                                                <i class="fas fa-wallet"></i> Gestionar
                                            </a>
                                            @if($box->activeSession)
                                                <span class="summary-chip cash-box-balance-chip">${{ number_format($box->activeSession->currentBalance(), 2) }}</span>
                                            @endif
                                        </div>
                                    </article>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card module-card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-bolt"></i> Sesiones abiertas</h5>
                    </div>
                    <div class="card-body">
                        <div class="module-list">
                            @forelse($openSessions as $session)
                                <div class="module-list-item">
                                    <div>
                                        <strong>{{ $session->box->name }}</strong>
                                        <div class="table-note">{{ $session->user?->name ?? 'Sin responsable' }}</div>
                                        <div class="table-note">Base: ${{ number_format($session->opening_balance, 2) }}</div>
                                    </div>
                                    <span class="summary-chip">${{ number_format($session->currentBalance(), 2) }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No hay cajas abiertas en este momento.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
