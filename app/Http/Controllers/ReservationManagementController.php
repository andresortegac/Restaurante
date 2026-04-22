<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ReservationManagementController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->denyIfUnauthorized($this->reservationPermissions())) {
            return $response;
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in($this->availableStatuses())],
            'date' => ['nullable', 'date'],
        ]);

        $reservations = Reservation::query()
            ->with(['customer', 'table', 'reservedBy'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('customer_name', 'like', '%' . $search . '%')
                        ->orWhere('customer_phone', 'like', '%' . $search . '%')
                        ->orWhere('customer_email', 'like', '%' . $search . '%')
                        ->orWhere('notes', 'like', '%' . $search . '%')
                        ->orWhere('special_requests', 'like', '%' . $search . '%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['date'] ?? null, fn ($query, string $date) => $query->whereDate('reservation_at', $date))
            ->orderBy('reservation_at')
            ->paginate(15)
            ->withQueryString();

        return view('reservations.index', [
            'reservations' => $reservations,
            'filters' => $filters,
            'summary' => [
                'total' => Reservation::query()->count(),
                'today' => Reservation::query()->whereDate('reservation_at', today())->count(),
                'upcoming' => Reservation::query()
                    ->where('reservation_at', '>=', now())
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->count(),
                'confirmed' => Reservation::query()->where('status', 'confirmed')->count(),
            ],
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    public function create()
    {
        if ($response = $this->denyIfUnauthorized(['reservations.create'])) {
            return $response;
        }

        return view('reservations.form', [
            'pageTitle' => 'Nueva reserva',
            'reservation' => new Reservation([
                'status' => 'pending',
                'party_size' => 2,
                'reservation_at' => now()->addHour()->format('Y-m-d\TH:i'),
                'source' => 'Telefono',
            ]),
            'formAction' => route('reservations.store'),
            'submitLabel' => 'Guardar reserva',
            'customers' => $this->availableCustomers(),
            'tables' => $this->availableTables(),
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['reservations.create'])) {
            return $response;
        }

        $validated = $this->validateReservationData($request);
        $payload = $this->buildReservationPayload($validated);
        $payload['reserved_by'] = auth()->id();

        Reservation::create($payload);

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Reserva creada correctamente.');
    }

    public function edit(Reservation $reservation)
    {
        if ($response = $this->denyIfUnauthorized(['reservations.edit'])) {
            return $response;
        }

        return view('reservations.form', [
            'pageTitle' => 'Editar reserva',
            'reservation' => $reservation,
            'formAction' => route('reservations.update', $reservation),
            'submitLabel' => 'Actualizar reserva',
            'customers' => $this->availableCustomers(),
            'tables' => $this->availableTables(),
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['reservations.edit'])) {
            return $response;
        }

        $validated = $this->validateReservationData($request);
        $reservation->update($this->buildReservationPayload($validated));

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Reserva actualizada correctamente.');
    }

    public function destroy(Reservation $reservation): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['reservations.delete'])) {
            return $response;
        }

        if ($reservation->reservation_at?->isPast() || in_array($reservation->status, ['seated', 'completed', 'no_show'], true)) {
            $reservation->update(['status' => 'cancelled']);

            return redirect()
                ->route('reservations.index')
                ->with('warning', 'La reserva ya forma parte del historial operativo. Se marco como cancelada para conservar trazabilidad.');
        }

        $reservation->delete();

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Reserva eliminada correctamente.');
    }

    private function validateReservationData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'restaurant_table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'reservation_at' => ['required', 'date'],
            'party_size' => ['required', 'integer', 'min:1', 'max:30'],
            'status' => ['required', Rule::in($this->availableStatuses())],
            'source' => ['nullable', 'string', 'max:100'],
            'special_requests' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function buildReservationPayload(array $validated): array
    {
        return [
            'customer_id' => $validated['customer_id'] ?? null,
            'restaurant_table_id' => $validated['restaurant_table_id'] ?? null,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'customer_email' => $validated['customer_email'] ?? null,
            'reservation_at' => $validated['reservation_at'],
            'party_size' => $validated['party_size'],
            'status' => $validated['status'],
            'source' => $validated['source'] ?? null,
            'special_requests' => $validated['special_requests'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function availableCustomers()
    {
        return Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);
    }

    private function availableTables()
    {
        return RestaurantTable::query()
            ->active()
            ->orderBy('area')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'area', 'capacity']);
    }

    private function availableStatuses(): array
    {
        return array_keys($this->statusLabels());
    }

    private function statusLabels(): array
    {
        return [
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmada',
            'seated' => 'Ya llego',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            'no_show' => 'No asistio',
        ];
    }

    private function reservationPermissions(): array
    {
        return ['reservations.view', 'reservations.create', 'reservations.edit', 'reservations.delete'];
    }

    private function denyIfUnauthorized(array $permissions): ?Response
    {
        $user = auth()->user();

        if ($user && ($user->hasRole('Admin') || $user->hasAnyPermission($permissions))) {
            return null;
        }

        return response()->view('errors.403', [], 403);
    }
}
