@extends('layouts.app')

@section('title', 'Domicilios - RestaurantePOS')

@section('content')
    @php
        $canCreateDelivery = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('deliveries.create');
        $canEditDelivery = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('deliveries.edit');
        $canDeleteDelivery = Auth::user()->hasRole('Admin') || Auth::user()->hasPermission('deliveries.delete');
    @endphp

    <div class="module-page">
        <section class="module-hero">
            <div>
                <span class="module-kicker">Pedidos / Domicilios</span>
                <h1>Gestión de domicilios</h1>
                <p>Administra pedidos para entrega, controla estados del reparto y asigna responsables cuando sea necesario.</p>
            </div>
            <div class="summary-group">
                <span class="summary-chip">{{ $summary['total'] }} registrados</span>
                <span class="summary-chip">{{ $summary['pending'] }} en proceso</span>
                <span class="summary-chip">{{ $summary['delivered'] }} entregados</span>
                <span class="summary-chip">{{ $summary['cancelled'] }} cancelados</span>
            </div>
        </section>

        <div class="module-toolbar">
            <form method="GET" action="{{ route('deliveries.index') }}" class="row g-2 align-items-end flex-grow-1">
                <div class="col-md-5">
                    <label class="form-label" for="search">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Número, cliente, teléfono o dirección">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        @foreach(['pending' => 'Pendiente', 'assigned' => 'Asignado', 'in_transit' => 'En camino', 'delivered' => 'Entregado', 'cancelled' => 'Cancelado'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="assigned_user_id">Responsable</label>
                    <select class="form-select" id="assigned_user_id" name="assigned_user_id">
                        <option value="">Todos</option>
                        @foreach($deliveryUsers as $deliveryUser)
                            <option value="{{ $deliveryUser->id }}" @selected((string) ($filters['assigned_user_id'] ?? '') === (string) $deliveryUser->id)>{{ $deliveryUser->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                </div>
            </form>

            @if($canCreateDelivery)
                <a href="{{ route('deliveries.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo domicilio
                </a>
            @endif
        </div>

        <div class="card module-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Domicilio</th>
                                <th>Cliente</th>
                                <th>Entrega</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($deliveries as $delivery)
                                <tr>
                                    <td>
                                        <strong>{{ $delivery->delivery_number }}</strong>
                                        <div class="table-note">{{ $delivery->created_at?->format('d/m/Y H:i') ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $delivery->customer_name }}</div>
                                        <div class="table-note">{{ $delivery->customer_phone ?: 'Sin teléfono' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $delivery->assignedUser?->name ?? 'Sin asignar' }}</div>
                                        <div class="table-note">{{ $delivery->delivery_address }}</div>
                                    </td>
                                    <td>
                                        <div>${{ number_format($delivery->total_charge, 2) }}</div>
                                        <div class="table-note">Pedido ${{ number_format($delivery->order_total, 2) }} + envío ${{ number_format($delivery->delivery_fee, 2) }}</div>
                                    </td>
                                    <td>
                                        @php
                                            $statusMap = [
                                                'pending' => ['Pendiente', 'bg-secondary'],
                                                'assigned' => ['Asignado', 'bg-info'],
                                                'in_transit' => ['En camino', 'bg-warning text-dark'],
                                                'delivered' => ['Entregado', 'bg-success'],
                                                'cancelled' => ['Cancelado', 'bg-danger'],
                                            ];
                                            [$statusLabel, $statusClass] = $statusMap[$delivery->status];
                                        @endphp
                                        <span class="badge rounded-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td>
                                        <div class="table-actions justify-content-end">
                                            @if($canEditDelivery)
                                                <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-outline-primary btn-sm">Editar</a>
                                            @endif
                                            @if($canDeleteDelivery)
                                                <form method="POST" action="{{ route('deliveries.destroy', $delivery) }}" onsubmit="return confirm('Deseas eliminar este domicilio?');">
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
                                    <td colspan="6" class="text-center py-4 text-muted">Todavía no hay domicilios registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $deliveries->links() }}
        </div>
    </div>
@endsection
