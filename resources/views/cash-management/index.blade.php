@extends('layouts.app')

@section('title', 'Gestion de Caja - RestaurantePOS')

@section('content')
    @php
        $canManageBoxCatalog = Auth::user()->hasRole('Admin');
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">POS / Gestion de Caja</span>
                <h1>Cajas y sesiones activas</h1>
                <p>Diferencia la configuracion fija de cada caja del flujo diario de apertura, movimientos y cierre del turno.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['boxes'] }} cajas</span>
                <span class="summary-chip">{{ $summary['openSessions'] }} abiertas</span>
                <span class="summary-chip">{{ $summary['closedToday'] }} cierres hoy</span>
                <span class="summary-chip">${{ number_format($summary['todayIncome'], 2) }} ingresos hoy</span>
                <span class="summary-chip">${{ number_format($summary['todayExpense'], 2) }} egresos hoy</span>
            </div>
        </section>

        <div class="card module-card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-4">
                    <div>
                        <div class="summary-kicker mb-2">Como funciona el flujo de caja</div>
                        <div class="row g-3">
                            <div class="col-md-6 col-xl-3">
                                <div class="meta-box h-100">
                                    <div class="fw-bold">1. Crear caja</div>
                                    <div class="seat-note">Se hace una sola vez por cada punto fisico de cobro, por ejemplo caja principal o barra.</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="meta-box h-100">
                                    <div class="fw-bold">2. Abrir sesion</div>
                                    <div class="seat-note">Al iniciar el turno, el cajero abre la caja e ingresa la base inicial.</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="meta-box h-100">
                                    <div class="fw-bold">3. Operar el dia</div>
                                    <div class="seat-note">Las ventas y movimientos manuales actualizan el saldo esperado automaticamente.</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="meta-box h-100">
                                    <div class="fw-bold">4. Cierre diario</div>
                                    <div class="seat-note">Al final del turno se cuenta el efectivo, se compara contra el sistema y se registra la diferencia.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        @if($canManageBoxCatalog)
                            <a href="{{ route('cash-management.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Crear caja
                            </a>
                        @endif
                        <a href="{{ route('cash-management.history') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-clock-rotate-left"></i> Historial
                        </a>
                        <a href="{{ route('cash-management.monthly') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-calendar-days"></i> Cierre mensual
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card module-card h-100">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <h5 class="card-title mb-0"><i class="fas fa-wallet"></i> Cajas registradas</h5>
                        <div class="table-note">Una caja se crea una vez; lo que abres y cierras cada dia es su sesion.</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach($boxes as $box)
                                @php
                                    $boxHasSessions = $box->sessions_count > 0;
                                    $boxStatusLabel = $box->activeSession
                                        ? 'Sesion abierta'
                                        : ($boxHasSessions ? 'Lista para nuevo turno' : 'Pendiente de primera apertura');
                                    $boxStatusClass = $box->activeSession
                                        ? 'bg-success'
                                        : ($boxHasSessions ? 'bg-secondary' : 'bg-warning text-dark');
                                @endphp
                                <div class="col-md-6">
                                    <article class="cash-box-card h-100">
                                        <div class="cash-box-header">
                                            <div>
                                                <h5 class="mb-1">{{ $box->name }}</h5>
                                                <div class="table-note">{{ $box->code }}</div>
                                            </div>
                                            <span class="badge rounded-pill cash-box-status {{ $boxStatusClass }}">
                                                {{ $boxStatusLabel }}
                                            </span>
                                        </div>
                                        <div class="cash-box-meta">
                                            <div class="table-note">
                                                Responsable actual: {{ $box->activeSession?->user?->name ?? $box->user?->name ?? 'Sin asignar' }}
                                            </div>
                                            <div class="table-note">
                                                @if($box->activeSession)
                                                    Apertura en curso desde {{ $box->activeSession->opened_at?->format('d/m/Y H:i') ?? 'hoy' }}
                                                @elseif($boxHasSessions)
                                                    Ultimo cierre: {{ $box->closed_at ? $box->closed_at->format('d/m/Y H:i') : 'Sesion previa registrada' }}
                                                @else
                                                    Caja creada pero aun sin sesiones operativas
                                                @endif
                                            </div>
                                            <div class="table-note">Sesiones acumuladas: {{ $box->sessions_count }}</div>
                                        </div>
                                        <div class="cash-box-actions">
                                            <a href="{{ route('cash-management.show', $box) }}" class="btn btn-primary">
                                                <i class="fas {{ $box->activeSession ? 'fa-right-from-bracket' : 'fa-lock-open' }}"></i>
                                                {{ $box->activeSession ? 'Gestionar cierre' : 'Preparar apertura' }}
                                            </a>
                                            @if($canManageBoxCatalog)
                                                <a href="{{ route('cash-management.edit', $box) }}" class="btn btn-outline-secondary">
                                                    <i class="fas fa-pen"></i> Editar
                                                </a>
                                            @endif
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
                                    <div class="d-flex flex-column align-items-end gap-2">
                                        <span class="summary-chip">${{ number_format($session->currentBalance(), 2) }}</span>
                                        <a href="{{ route('cash-management.show', $session->box) }}" class="btn btn-sm btn-outline-primary">Ver cierre</a>
                                    </div>
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
