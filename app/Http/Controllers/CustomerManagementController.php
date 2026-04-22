<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CustomerManagementController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->denyIfUnauthorized($this->customerPermissions())) {
            return $response;
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $customers = Customer::query()
            ->withCount(['tableOrders', 'sales'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('document_number', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'filters' => $filters,
            'summary' => [
                'total' => Customer::query()->count(),
                'active' => Customer::query()->where('is_active', true)->count(),
                'inactive' => Customer::query()->where('is_active', false)->count(),
            ],
        ]);
    }

    public function create()
    {
        if ($response = $this->denyIfUnauthorized(['customers.create'])) {
            return $response;
        }

        return view('customers.form', [
            'pageTitle' => 'Nuevo cliente',
            'customer' => new Customer(['is_active' => true]),
            'formAction' => route('customers.store'),
            'submitLabel' => 'Guardar cliente',
        ]);
    }

    public function store(Request $request): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['customers.create'])) {
            return $response;
        }

        $validated = $this->validateCustomerData($request);

        Customer::create($validated);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Cliente creado correctamente.');
    }

    public function edit(Customer $customer)
    {
        if ($response = $this->denyIfUnauthorized(['customers.edit'])) {
            return $response;
        }

        return view('customers.form', [
            'pageTitle' => 'Editar cliente',
            'customer' => $customer,
            'formAction' => route('customers.update', $customer),
            'submitLabel' => 'Actualizar cliente',
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['customers.edit'])) {
            return $response;
        }

        $validated = $this->validateCustomerData($request, $customer);

        $customer->update($validated);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Customer $customer): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['customers.delete'])) {
            return $response;
        }

        if ($customer->tableOrders()->exists() || $customer->sales()->exists()) {
            $customer->update(['is_active' => false]);

            return redirect()
                ->route('customers.index')
                ->with('warning', 'El cliente ya tiene movimientos registrados. Se desactivo para conservar el historico.');
        }

        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Cliente eliminado correctamente.');
    }

    public function search(Request $request)
    {
        if ($response = $this->denyIfUnauthorized(['customers.view'])) {
            return $response;
        }

        $term = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->where('is_active', true)
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($nestedQuery) use ($term) {
                    $nestedQuery
                        ->where('name', 'like', '%' . $term . '%')
                        ->orWhere('document_number', 'like', '%' . $term . '%')
                        ->orWhere('phone', 'like', '%' . $term . '%')
                        ->orWhere('email', 'like', '%' . $term . '%');
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'document_number', 'phone', 'email']);

        return response()->json($customers);
    }

    private function validateCustomerData(Request $request, ?Customer $customer = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255', Rule::unique('customers', 'document_number')->ignore($customer?->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer?->id)],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function customerPermissions(): array
    {
        return ['customers.view', 'customers.create', 'customers.edit', 'customers.delete'];
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
