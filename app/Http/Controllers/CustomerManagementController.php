<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\CustomerPaymentReceipt;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Services\CustomerBalanceService;
use App\Services\CustomerCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
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
            ->withSum([
                'balanceMovements as consumed_balance_total' => fn ($query) => $query->where('movement_type', 'sale_consumption'),
            ], 'amount')
            ->withSum([
                'balanceMovements as paid_balance_total' => fn ($query) => $query->where('movement_type', 'customer_payment'),
            ], 'amount')
            ->withSum([
                'pendingCredits as pending_credit_total' => fn ($query) => $query->where('status', 'pending'),
            ], 'balance')
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
                'customersWithAvailableBalance' => Customer::query()
                    ->where('available_balance', '>', 0)
                    ->count(),
                'availableBalance' => (float) Customer::query()->sum('available_balance'),
                'consumedBalance' => money_value(max(0,
                    abs((float) \App\Models\CustomerBalanceMovement::query()
                        ->where('movement_type', 'sale_consumption')
                        ->sum('amount'))
                    - (float) \App\Models\CustomerBalanceMovement::query()
                        ->where('movement_type', 'customer_payment')
                        ->sum('amount')
                )),
            ],
        ]);
    }

    public function credits(Request $request)
    {
        if ($response = $this->denyIfUnauthorized(['customers.view'])) {
            return $response;
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'balance' => ['nullable', 'in:all,favor'],
        ]);

        $customers = Customer::query()
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('document_number', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->when(($filters['balance'] ?? 'favor') === 'favor', fn ($query) => $query->where('available_balance', '>', 0))
            ->orderByDesc('available_balance')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('customers.credits.index', [
            'customers' => $customers,
            'filters' => $filters,
            'summary' => [
                'customersWithAvailableBalance' => Customer::query()
                    ->where('available_balance', '>', 0)
                    ->count(),
                'availableBalance' => (float) Customer::query()->sum('available_balance'),
            ],
        ]);
    }

    public function showCredit(Customer $customer)
    {
        if ($response = $this->denyIfUnauthorized(['customers.view'])) {
            return $response;
        }

        $this->loadCustomerCreditSummary($customer);

        return view('customers.credits.show', [
            'customer' => $customer,
            'summary' => $this->customerCreditSummary($customer),
        ]);
    }

    public function showCreditHistory(Customer $customer)
    {
        if ($response = $this->denyIfUnauthorized(['customers.view'])) {
            return $response;
        }

        $this->loadCustomerCreditSummary($customer);

        $credits = $this->customerCreditsQuery($customer)
            ->paginate(15);

        return view('customers.credits.history', [
            'customer' => $customer,
            'credits' => $credits,
            'summary' => $this->customerCreditSummary($customer),
        ]);
    }

    public function showCollect(Customer $customer)
    {
        if ($response = $this->denyIfUnauthorized(['customers.edit'])) {
            return $response;
        }

        $this->loadCustomerCreditSummary($customer);

        $pendingCredits = $this->customerCreditsQuery($customer)
            ->where('status', 'pending')
            ->get();

        $recentReceipts = $this->customerPaymentReceiptsQuery($customer)
            ->limit(5)
            ->get();

        return view('customers.credits.collect', [
            'customer' => $customer,
            'summary' => $this->customerCreditSummary($customer),
            'pendingCredits' => $pendingCredits,
            'balanceDebt' => $this->customerBalanceDebt($customer),
            'paymentMethods' => PaymentMethod::query()->systemAllowed()->orderBy('name')->get(),
            'recentReceipts' => $recentReceipts,
        ]);
    }

    public function showPaymentHistory(Request $request, Customer $customer)
    {
        if ($response = $this->denyIfUnauthorized(['customers.view'])) {
            return $response;
        }

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'invoice' => ['nullable', 'string', 'max:255'],
        ]);

        $this->loadCustomerCreditSummary($customer);

        $matchingSaleIds = collect();

        if (filled($filters['invoice'] ?? null)) {
            $invoice = (string) $filters['invoice'];

            $matchingSaleIds = Sale::query()
                ->where('customer_id', $customer->id)
                ->where(function ($query) use ($invoice) {
                    $query
                        ->where('id', $invoice)
                        ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', '%' . $invoice . '%'))
                        ->orWhereHas('tableOrder', fn ($orderQuery) => $orderQuery->where('order_number', 'like', '%' . $invoice . '%'));
                })
                ->pluck('id');
        }

        $receipts = $this->customerPaymentReceiptsQuery($customer)
            ->when($filters['date_from'] ?? null, fn ($query, string $dateFrom) => $query->whereDate('paid_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, string $dateTo) => $query->whereDate('paid_at', '<=', $dateTo))
            ->when($filters['invoice'] ?? null, function ($query, string $invoice) use ($matchingSaleIds) {
                $query->where(function ($nestedQuery) use ($invoice, $matchingSaleIds) {
                    $nestedQuery
                        ->where('receipt_number', 'like', '%' . $invoice . '%')
                        ->orWhere('reference', 'like', '%' . $invoice . '%');

                    foreach ($matchingSaleIds as $saleId) {
                        $nestedQuery->orWhere('allocations', 'like', '%"sale_id":' . $saleId . '%');
                    }
                });
            })
            ->paginate(15)
            ->withQueryString();

        return view('customers.credits.payment-history', [
            'customer' => $customer,
            'summary' => $this->customerCreditSummary($customer),
            'receipts' => $receipts,
            'filters' => $filters,
        ]);
    }

    public function printPaymentReceipt(Customer $customer, CustomerPaymentReceipt $receipt)
    {
        if ($response = $this->denyIfUnauthorized(['customers.view'])) {
            return $response;
        }

        if ((int) $receipt->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $receipt->load(['customer', 'paymentMethod', 'box', 'receivedBy']);

        return view('customers.credits.print-payment-receipt', [
            'customer' => $customer,
            'receipt' => $receipt,
        ]);
    }

    public function showBalanceHistory(Request $request, Customer $customer)
    {
        if ($response = $this->denyIfUnauthorized(['customers.view'])) {
            return $response;
        }

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'ticket' => ['nullable', 'string', 'max:255'],
            'printable' => ['nullable', 'boolean'],
        ]);

        $this->loadCustomerCreditSummary($customer);

        $movements = $this->customerBalanceMovementsQuery($customer)
            ->when($filters['date_from'] ?? null, fn ($query, string $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, string $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($filters['ticket'] ?? null, function ($query, string $ticket) {
                $query->whereHas('sale', function ($saleQuery) use ($ticket) {
                    $saleQuery
                        ->where('id', $ticket)
                        ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', '%' . $ticket . '%'))
                        ->orWhereHas('tableOrder', fn ($orderQuery) => $orderQuery->where('order_number', 'like', '%' . $ticket . '%'));
                });
            })
            ->when($request->boolean('printable'), fn ($query) => $query->whereNotNull('sale_id'))
            ->paginate(15)
            ->withQueryString();

        return view('customers.credits.balance-history', [
            'customer' => $customer,
            'movements' => $movements,
            'filters' => $filters,
            'summary' => $this->customerCreditSummary($customer),
        ]);
    }

    public function showConsumedInvoices(Request $request, Customer $customer)
    {
        if ($response = $this->denyIfUnauthorized(['customers.view'])) {
            return $response;
        }

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'invoice' => ['nullable', 'string', 'max:255'],
        ]);

        $this->loadCustomerCreditSummary($customer);

        $movements = $this->customerBalanceMovementsQuery($customer)
            ->where('movement_type', 'sale_consumption')
            ->whereNotNull('sale_id')
            ->when($filters['date_from'] ?? null, fn ($query, string $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, string $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($filters['invoice'] ?? null, function ($query, string $invoice) {
                $query->whereHas('sale', function ($saleQuery) use ($invoice) {
                    $saleQuery
                        ->where('id', $invoice)
                        ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', '%' . $invoice . '%'))
                        ->orWhereHas('tableOrder', fn ($orderQuery) => $orderQuery->where('order_number', 'like', '%' . $invoice . '%'));
                });
            })
            ->paginate(15)
            ->withQueryString();

        return view('customers.credits.consumed-invoices', [
            'customer' => $customer,
            'movements' => $movements,
            'filters' => $filters,
            'summary' => $this->customerCreditSummary($customer),
            'consumedTotal' => abs((float) $customer->balanceMovements()
                ->where('movement_type', 'sale_consumption')
                ->sum('amount')),
        ]);
    }

    public function printDebtSummary(Customer $customer)
    {
        if ($response = $this->denyIfUnauthorized(['customers.view'])) {
            return $response;
        }

        $this->loadCustomerCreditSummary($customer);

        $credits = $this->customerCreditsQuery($customer)
            ->where('status', 'pending')
            ->get();

        return view('customers.credits.print-debt-summary', [
            'customer' => $customer,
            'credits' => $credits,
            'balanceDebt' => $this->customerBalanceDebt($customer),
            'summary' => $this->customerCreditSummary($customer),
            'printedAt' => now(),
        ]);
    }

    public function storeCredit(Request $request, Customer $customer, CustomerCreditService $customerCreditService): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['customers.edit'])) {
            return $response;
        }

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $customerCreditService->assignPendingBalance($customer, $validated, Auth::id());

        return redirect()
            ->route('customers.credits.show', $customer)
            ->with('success', 'Saldo pendiente asignado correctamente.');
    }

    public function storeBalanceMovement(Request $request, Customer $customer, CustomerBalanceService $customerBalanceService): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['customers.edit'])) {
            return $response;
        }

        $validated = $request->validate([
            'operation' => ['required', 'in:add,remove'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $customerBalanceService->registerManualMovement($customer, $validated, Auth::id());

        return redirect()
            ->route('customers.credits.show', $customer)
            ->with('success', $validated['operation'] === 'remove'
                ? 'Saldo a favor descontado correctamente.'
                : 'Saldo a favor agregado correctamente.');
    }

    public function payCredit(Request $request, Customer $customer, CustomerCredit $credit, CustomerCreditService $customerCreditService): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['customers.edit'])) {
            return $response;
        }

        if ((int) $credit->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if ($credit->status !== 'pending') {
            return redirect()
                ->route('customers.credits.show', $customer)
                ->with('info', 'Este saldo ya fue pagado.');
        }

        $validated = $request->validate([
            'payment_method_id' => ['nullable', $this->allowedPaymentMethodRule()],
            'amount_received' => ['required', 'numeric', 'gt:0', 'max:' . (float) $credit->balance],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $customerCreditService->payAssignedCredit($credit, $validated, Auth::id());

        return redirect()
            ->route('customers.credits.show', $customer)
            ->with('success', 'Saldo cobrado correctamente.');
    }

    public function collectCredit(Request $request, Customer $customer, CustomerCreditService $customerCreditService): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['customers.edit'])) {
            return $response;
        }

        $totalPending = money_value((float) $customer->pendingCredits()->sum('balance') + $this->customerBalanceDebt($customer));

        if ($totalPending <= 0) {
            return redirect()
                ->route('customers.credits.show', $customer)
                ->with('info', 'Este cliente no tiene deuda pendiente.');
        }

        $paymentMode = (string) $request->input('payment_mode', 'partial');

        if ($paymentMode === 'full') {
            $request->merge([
                'amount_received' => $totalPending,
            ]);
        }

        $validated = $request->validate([
            'payment_mode' => ['nullable', 'in:full,partial'],
            'payment_method_id' => ['nullable', $this->allowedPaymentMethodRule()],
            'amount_received' => ['required', 'numeric', 'gt:0', 'max:' . $totalPending],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $customerCreditService->payCustomerBalance($customer, $validated, Auth::id());

        return redirect()
            ->route('customers.credits.receipts.print', [$customer, $result['receipt']])
            ->with('success', $result['remaining_pending'] <= 0
                ? 'La deuda del cliente fue cobrada completamente.'
                : 'Abono registrado correctamente.');
    }

    public function create()
    {
        if ($response = $this->denyIfUnauthorized(['customers.create'])) {
            return $response;
        }

        return view('customers.form', [
            'pageTitle' => 'Nuevo cliente',
            'customer' => new Customer($this->defaultCustomerData()),
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
            'document_number' => ['required', 'string', 'max:255', Rule::unique('customers', 'document_number')->ignore($customer?->id)],
            'billing_identification' => ['nullable', 'string', 'max:255'],
            'identification_document_code' => ['nullable', 'string', 'max:10'],
            'legal_organization_code' => ['nullable', 'string', 'max:10'],
            'tribute_code' => ['nullable', 'string', 'max:10'],
            'municipality_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:50'],
            'billing_address' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer?->id)],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Escribe el nombre o razón social del cliente.',
            'document_number.required' => 'Escribe el documento o NIT del cliente.',
            'document_number.unique' => 'Ya existe un cliente registrado con ese documento o NIT.',
            'phone.required' => 'Escribe un teléfono de contacto.',
            'billing_address.required' => 'Escribe la dirección de facturación del cliente.',
            'email.required' => 'Escribe el correo electrónico del cliente.',
            'email.email' => 'Escribe un correo electrónico válido, por ejemplo cliente@correo.com.',
            'email.unique' => 'Ya existe un cliente registrado con ese correo electrónico.',
        ], [
            'name' => 'nombre o razón social',
            'document_number' => 'documento o NIT',
            'billing_identification' => 'identificación de facturación',
            'identification_document_code' => 'tipo de documento',
            'legal_organization_code' => 'tipo de persona',
            'tribute_code' => 'responsabilidad tributaria',
            'municipality_code' => 'municipio DIAN',
            'phone' => 'teléfono',
            'billing_address' => 'dirección de facturación',
            'trade_name' => 'nombre comercial',
            'email' => 'correo electrónico',
            'notes' => 'notas',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['billing_identification'] = filled($validated['billing_identification'] ?? null) ? $validated['billing_identification'] : $validated['document_number'];
        $validated['identification_document_code'] = filled($validated['identification_document_code'] ?? null) ? $validated['identification_document_code'] : config('factus.default_identification_document_code', '13');
        $validated['legal_organization_code'] = filled($validated['legal_organization_code'] ?? null) ? $validated['legal_organization_code'] : config('factus.default_legal_organization_code', '2');
        $validated['tribute_code'] = filled($validated['tribute_code'] ?? null) ? $validated['tribute_code'] : config('factus.default_tribute_code', 'ZZ');
        $validated['municipality_code'] = filled($validated['municipality_code'] ?? null) ? $validated['municipality_code'] : config('factus.default_municipality_code', '68001');
        $validated['trade_name'] = filled($validated['trade_name'] ?? null) ? $validated['trade_name'] : $validated['name'];

        return $validated;
    }

    private function defaultCustomerData(): array
    {
        return [
            'is_active' => true,
            'identification_document_code' => config('factus.default_identification_document_code', '13'),
            'legal_organization_code' => config('factus.default_legal_organization_code', '2'),
            'tribute_code' => config('factus.default_tribute_code', 'ZZ'),
            'municipality_code' => config('factus.default_municipality_code', '68001'),
        ];
    }

    private function customerPermissions(): array
    {
        return ['customers.view', 'customers.create', 'customers.edit', 'customers.delete'];
    }

    private function loadCustomerCreditSummary(Customer $customer): void
    {
        $customer->loadCount([
            'pendingCredits',
            'credits as paid_credits_count' => fn ($query) => $query->where('status', 'paid'),
        ])->loadSum([
            'pendingCredits as pending_credit_total' => fn ($query) => $query->where('status', 'pending'),
        ], 'balance');
    }

    private function customerCreditsQuery(Customer $customer)
    {
        return $customer->credits()
            ->with(['sale.tableOrder.table', 'paymentMethod', 'createdBy'])
            ->latest()
            ->orderByDesc('id');
    }

    private function customerBalanceMovementsQuery(Customer $customer)
    {
        return $customer->balanceMovements()
            ->with(['sale.tableOrder.table', 'sale.invoice', 'sale.payments.paymentMethod', 'createdBy'])
            ->latest()
            ->orderByDesc('id');
    }

    private function customerPaymentReceiptsQuery(Customer $customer)
    {
        return $customer->paymentReceipts()
            ->with(['paymentMethod', 'box', 'receivedBy'])
            ->latest('paid_at')
            ->latest('id');
    }

    private function customerCreditSummary(Customer $customer): array
    {
        $grossConsumedBalance = abs((float) $customer->balanceMovements()
            ->where('movement_type', 'sale_consumption')
            ->sum('amount'));
        $balanceDebt = $this->customerBalanceDebt($customer);
        $availableBalance = money_value($customer->available_balance ?? 0);
        $balanceTop = money_value($availableBalance + $balanceDebt);
        $pendingCredits = money_value((float) ($customer->pending_credit_total ?? 0));

        return [
            'pending' => money_value($pendingCredits + $balanceDebt),
            'pendingCredits' => $pendingCredits,
            'balanceDebt' => $balanceDebt,
            'pendingCount' => (int) ($customer->pending_credits_count ?? 0) + ($balanceDebt > 0 ? 1 : 0),
            'paidCount' => (int) ($customer->paid_credits_count ?? 0),
            'available' => $availableBalance,
            'consumed' => $balanceDebt,
            'grossConsumed' => $grossConsumedBalance,
            'top' => $balanceTop,
            'remainingToTop' => $availableBalance,
        ];
    }

    private function customerBalanceDebt(Customer $customer): int
    {
        $consumed = abs((float) $customer->balanceMovements()
            ->where('movement_type', 'sale_consumption')
            ->sum('amount'));
        $paid = (float) $customer->balanceMovements()
            ->where('movement_type', 'customer_payment')
            ->sum('amount');

        return money_value(max(0, $consumed - $paid));
    }

    private function allowedPaymentMethodRule()
    {
        return Rule::exists('payment_methods', 'id')
            ->where('active', true)
            ->whereIn('code', PaymentMethod::SYSTEM_ALLOWED_CODES);
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
