<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CashManagementController extends Controller
{
    public function index()
    {
        if ($response = $this->denyIfUnauthorized($this->boxPermissions())) {
            return $response;
        }

        $boxes = Box::query()
            ->with(['activeSession.user', 'user'])
            ->withCount('sessions')
            ->orderBy('name')
            ->get();

        $openSessions = BoxSession::query()
            ->with(['box', 'user'])
            ->where('status', 'open')
            ->orderByDesc('opened_at')
            ->get();

        return view('cash-management.index', [
            'boxes' => $boxes,
            'openSessions' => $openSessions,
            'summary' => [
                'boxes' => $boxes->count(),
                'openSessions' => $openSessions->count(),
                'closedToday' => BoxSession::query()
                    ->whereDate('closed_at', today())
                    ->count(),
                'todayIncome' => (float) BoxMovement::query()
                    ->whereDate('occurred_at', today())
                    ->where('amount', '>', 0)
                    ->sum('amount'),
                'todayExpense' => abs((float) BoxMovement::query()
                    ->whereDate('occurred_at', today())
                    ->where('amount', '<', 0)
                    ->sum('amount')),
            ],
        ]);
    }

    public function create()
    {
        if ($response = $this->denyIfUnauthorizedForCatalog()) {
            return $response;
        }

        return view('cash-management.form', [
            'pageTitle' => 'Crear caja',
            'box' => new Box([
                'status' => 'closed',
            ]),
            'formAction' => route('cash-management.store'),
            'submitLabel' => 'Guardar caja',
        ]);
    }

    public function store(Request $request): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorizedForCatalog()) {
            return $response;
        }

        $validated = $this->validateBoxData($request);

        $box = Box::query()->create([
            'name' => $validated['name'],
            'description' => filled($validated['description'] ?? null) ? $validated['description'] : null,
            'code' => $this->generateUniqueBoxCode($validated['name']),
            'status' => 'closed',
            'user_id' => null,
            'opening_balance' => 0,
        ]);

        return redirect()
            ->route('cash-management.show', $box)
            ->with('success', 'Caja creada correctamente. Ahora puedes abrir su primera sesión.');
    }

    public function show(Box $box)
    {
        if ($response = $this->denyIfUnauthorized($this->boxPermissions())) {
            return $response;
        }

        $box->load(['activeSession.user', 'activeSession.closedBy', 'user', 'closedBy']);

        $currentSession = $box->activeSession ?: $box->sessions()
            ->with(['user', 'closedBy'])
            ->latest('opened_at')
            ->first();

        $automaticIncome = $currentSession
            ? (float) $currentSession->movements()
                ->where(fn ($query) => $this->nonVoidedSaleMovementScope($query))
                ->whereIn('movement_type', ['sale_income', 'table_order_payment', 'credit_payment', 'customer_credit_payment'])
                ->where('amount', '>', 0)
                ->sum('amount')
            : 0;
        $manualIncome = $currentSession
            ? (float) $currentSession->movements()
                ->where('movement_type', 'manual_income')
                ->sum('amount')
            : 0;
        $manualExpense = $currentSession
            ? abs((float) $currentSession->movements()
                ->where('movement_type', 'manual_expense')
                ->sum('amount'))
            : 0;
        $paymentBreakdown = $this->paymentMethodBreakdown($currentSession);

        return view('cash-management.show', [
            'box' => $box,
            'currentSession' => $currentSession,
            'incomeTotal' => $currentSession ? $currentSession->incomeTotal() : 0,
            'expenseTotal' => $currentSession ? $currentSession->expenseTotal() : 0,
            'currentBalance' => $currentSession ? $currentSession->currentBalance() : 0,
            'automaticIncome' => $automaticIncome,
            'manualIncome' => $manualIncome,
            'manualExpense' => $manualExpense,
            'paymentBreakdown' => $paymentBreakdown,
            'paymentMethods' => PaymentMethod::query()->systemAllowed()->orderBy('name')->get(['id', 'name', 'code']),
            'reportedPaymentTotal' => money_value((float) $paymentBreakdown->sum('total')),
            'canRegisterMovements' => $this->canAccess(['boxes.movements']),
        ]);
    }

    public function createMovement(Box $box)
    {
        if ($response = $this->denyIfUnauthorized(['boxes.movements'])) {
            return $response;
        }

        return redirect(route('cash-management.show', ['box' => $box, 'panel' => 'movement']) . '#manual-movement');
    }

    public function edit(Box $box)
    {
        if ($response = $this->denyIfUnauthorizedForCatalog()) {
            return $response;
        }

        return view('cash-management.form', [
            'pageTitle' => 'Editar caja',
            'box' => $box,
            'formAction' => route('cash-management.update', $box),
            'submitLabel' => 'Actualizar caja',
        ]);
    }

    public function update(Request $request, Box $box): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorizedForCatalog()) {
            return $response;
        }

        $validated = $this->validateBoxData($request, $box);

        $box->update([
            'name' => $validated['name'],
            'description' => filled($validated['description'] ?? null) ? $validated['description'] : null,
            'code' => $box->code ?: $this->generateUniqueBoxCode($validated['name'], $box->id),
        ]);

        return redirect()
            ->route('cash-management.show', $box)
            ->with('success', 'Caja actualizada correctamente.');
    }

    public function open(Request $request, Box $box): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['boxes.open'])) {
            return $response;
        }

        $validated = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($box, $validated): void {
            $lockedBox = Box::query()->lockForUpdate()->findOrFail($box->id);

            $boxActiveSession = BoxSession::query()
                ->with('user')
                ->where('box_id', $lockedBox->id)
                ->where('status', 'open')
                ->latest('opened_at')
                ->lockForUpdate()
                ->first();

            if ($boxActiveSession) {
                $responsibleUser = $boxActiveSession->user?->name
                    ? ' a cargo de ' . $boxActiveSession->user->name
                    : '';

                throw ValidationException::withMessages([
                    'opening_balance' => 'La caja "' . $lockedBox->name . '" ya tiene una sesion abierta' . $responsibleUser . '.',
                ]);
            }

            $userOpenSession = BoxSession::query()
                ->with('box')
                ->where('user_id', Auth::id())
                ->where('status', 'open')
                ->latest('opened_at')
                ->lockForUpdate()
                ->first();

            if ($userOpenSession) {
                $openBoxName = $userOpenSession->box?->name ?? 'otra caja';

                throw ValidationException::withMessages([
                    'opening_balance' => 'Ya tienes una sesion abierta en "' . $openBoxName . '" y debes cerrarla antes de abrir "' . $lockedBox->name . '".',
                ]);
            }

            $session = BoxSession::query()->create([
                'box_id' => $lockedBox->id,
                'user_id' => Auth::id(),
                'opening_balance' => money_value($validated['opening_balance']),
                'opening_notes' => $validated['opening_notes'] ?? null,
                'status' => 'open',
                'opened_at' => now(),
            ]);

            $lockedBox->update([
                'user_id' => Auth::id(),
                'closed_by_user_id' => null,
                'opening_balance' => $session->opening_balance,
                'opening_notes' => $session->opening_notes,
                'closing_balance' => null,
                'counted_balance' => null,
                'difference_amount' => null,
                'closing_notes' => null,
                'status' => 'open',
                'opened_at' => $session->opened_at,
                'closed_at' => null,
            ]);

            $this->logAudit(
                $lockedBox,
                $session,
                'box_opened',
                'Apertura de caja con base inicial de $' . money($session->opening_balance),
                ['opening_balance' => (float) $session->opening_balance]
            );
        });

        return redirect()
            ->route('cash-management.show', $box)
            ->with('success', 'Caja abierta correctamente.');
    }

    public function storeMovement(Request $request, Box $box): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['boxes.movements'])) {
            return $response;
        }

        $validated = $request->validate([
            'movement_type' => ['required', 'in:manual_income,manual_expense'],
            'payment_method_id' => ['nullable', $this->allowedPaymentMethodRule()],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($box, $validated): void {
            $session = BoxSession::query()
                ->where('box_id', $box->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->firstOrFail();

            $rawAmount = money_value($validated['amount']);
            $paymentMethod = null;

            if ($validated['movement_type'] === 'manual_income') {
                $paymentMethod = $this->manualMovementPaymentMethod($validated['payment_method_id'] ?? null);
            }

            $signedAmount = $validated['movement_type'] === 'manual_expense'
                ? -1 * $rawAmount
                : ($this->isCashPaymentMethod($paymentMethod) ? $rawAmount : 0);
            $reportedAmount = $validated['movement_type'] === 'manual_income'
                ? $rawAmount
                : abs($signedAmount);

            $balanceBefore = $session->currentBalance();
            $balanceAfter = money_value($balanceBefore + $signedAmount);
            $description = $paymentMethod
                ? $validated['description'] . ' | Metodo ' . $paymentMethod->name
                : $validated['description'];

            $movement = $session->movements()->create([
                'box_id' => $box->id,
                'box_session_id' => $session->id,
                'user_id' => Auth::id(),
                'movement_type' => $validated['movement_type'],
                'amount' => $signedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'occurred_at' => now(),
            ]);

            $this->logAudit(
                $box,
                $session,
                $validated['movement_type'],
                $description,
                [
                    'movement_id' => $movement->id,
                    'amount' => (float) $reportedAmount,
                    'box_impact' => (float) $signedAmount,
                    'payment_method_id' => $paymentMethod?->id,
                    'balance_after' => $balanceAfter,
                ]
            );
        });

        return redirect()
            ->route('cash-management.show', $box)
            ->with('success', 'Movimiento registrado correctamente.');
    }

    public function close(Request $request, Box $box): RedirectResponse|Response
    {
        if ($response = $this->denyIfUnauthorized(['boxes.close'])) {
            return $response;
        }

        $validated = $request->validate([
            'counted_balance' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($box, $validated): void {
            $lockedBox = Box::query()->lockForUpdate()->findOrFail($box->id);
            $session = BoxSession::query()
                ->where('box_id', $box->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->firstOrFail();

            $expectedBalance = $session->currentBalance();
            $countedBalance = money_value($validated['counted_balance']);
            $difference = money_value($countedBalance - $expectedBalance);

            $session->update([
                'status' => 'closed',
                'counted_balance' => $countedBalance,
                'difference_amount' => $difference,
                'closing_notes' => $validated['closing_notes'] ?? null,
                'closed_by_user_id' => Auth::id(),
                'closed_at' => now(),
            ]);

            $lockedBox->update([
                'status' => 'closed',
                'closing_balance' => $expectedBalance,
                'counted_balance' => $countedBalance,
                'difference_amount' => $difference,
                'closing_notes' => $validated['closing_notes'] ?? null,
                'closed_by_user_id' => Auth::id(),
                'closed_at' => $session->closed_at,
            ]);

            $this->logAudit(
                $lockedBox,
                $session,
                'box_closed',
                'Cierre de caja conciliado.',
                [
                    'expected_balance' => $expectedBalance,
                    'counted_balance' => $countedBalance,
                    'difference_amount' => $difference,
                ]
            );
        });

        return redirect()
            ->route('cash-management.show', $box)
            ->with('success', 'Caja cerrada correctamente.');
    }

    public function history(Request $request)
    {
        if ($response = $this->denyIfUnauthorized(['boxes.view', 'boxes.reports'])) {
            return $response;
        }

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'box_id' => ['nullable', 'integer', 'exists:boxes,id'],
        ]);

        $sessionsQuery = BoxSession::query()
            ->with(['box', 'user', 'closedBy'])
            ->withCount([
                'movements as reportable_movements_count' => fn ($query) => $this->reportableMovementScope($query),
            ])
            ->where('status', 'closed')
            ->when($filters['date_from'] ?? null, fn ($query, string $dateFrom) => $query->whereDate('closed_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, string $dateTo) => $query->whereDate('closed_at', '<=', $dateTo))
            ->when($filters['user_id'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['box_id'] ?? null, fn ($query, int $boxId) => $query->where('box_id', $boxId))
            ->latest('closed_at');

        $sessions = (clone $sessionsQuery)
            ->paginate(20)
            ->withQueryString();
        $this->attachHistoryTransferTotals($sessions->getCollection());

        return view('cash-management.history', [
            'sessions' => $sessions,
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'boxes' => Box::query()->orderBy('name')->get(['id', 'name']),
            'summary' => [
                'closures' => (clone $sessionsQuery)->count(),
                'today' => (clone $sessionsQuery)->whereDate('closed_at', today())->count(),
            ],
        ]);
    }

    public function showHistorySession(Request $request, BoxSession $session)
    {
        if ($response = $this->denyIfUnauthorized(['boxes.view', 'boxes.reports'])) {
            return $response;
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'in:cash,transfer'],
        ]);

        $session->load(['box', 'user', 'closedBy']);

        $paymentMethodFilter = $filters['payment_method'] ?? null;
        $paymentMethodCodes = [
            'cash' => 'CASH',
            'transfer' => 'TRANSFER',
        ];
        $filteredPaymentMethodCode = $paymentMethodFilter ? $paymentMethodCodes[$paymentMethodFilter] : null;
        $filteredPaymentMethodIds = $filteredPaymentMethodCode
            ? PaymentMethod::query()
                ->whereRaw('UPPER(code) = ?', [$filteredPaymentMethodCode])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];
        $auditMovementIdsByPaymentMethod = $filteredPaymentMethodIds
            ? BoxAuditLog::query()
                ->where('box_session_id', $session->id)
                ->get(['metadata'])
                ->filter(function (BoxAuditLog $auditLog) use ($filteredPaymentMethodIds): bool {
                    $movementId = $auditLog->metadata['movement_id'] ?? null;
                    $paymentMethodId = $auditLog->metadata['payment_method_id'] ?? null;

                    return $movementId && $paymentMethodId && in_array((int) $paymentMethodId, $filteredPaymentMethodIds, true);
                })
                ->map(fn (BoxAuditLog $auditLog) => (int) $auditLog->metadata['movement_id'])
                ->unique()
                ->values()
                ->all()
            : [];

        $movementsQuery = BoxMovement::query()
            ->with([
                'user',
                'payment.paymentMethod',
                'sale.customer',
                'sale.invoice',
                'sale.tableOrder.table',
                'sale.items',
            ])
            ->where('box_session_id', $session->id)
            ->where(fn ($query) => $this->nonVoidedSaleMovementScope($query))
            ->where(fn ($query) => $this->reportableMovementScope($query))
            ->when($filteredPaymentMethodCode, function ($query) use ($filteredPaymentMethodCode, $auditMovementIdsByPaymentMethod): void {
                $query->where(function ($nestedQuery) use ($filteredPaymentMethodCode, $auditMovementIdsByPaymentMethod): void {
                    $nestedQuery->whereHas('payment.paymentMethod', function ($paymentMethodQuery) use ($filteredPaymentMethodCode): void {
                        $paymentMethodQuery->whereRaw('UPPER(code) = ?', [$filteredPaymentMethodCode]);
                    });

                    if ($auditMovementIdsByPaymentMethod) {
                        $nestedQuery->orWhereIn('id', $auditMovementIdsByPaymentMethod);
                    }

                    if ($filteredPaymentMethodCode === 'CASH') {
                        $nestedQuery->orWhere(function ($cashQuery): void {
                            $cashQuery
                                ->whereNull('payment_id')
                                ->where(function ($cashMovementQuery): void {
                                    $cashMovementQuery
                                        ->where('amount', '<>', 0)
                                        ->orWhere('movement_type', 'manual_expense');
                                });
                        });
                    }
                });
            })
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('description', 'like', '%' . $search . '%')
                        ->orWhereHas('sale', function ($saleQuery) use ($search): void {
                            $saleQuery
                                ->where('id', $search)
                                ->orWhere('customer_name', 'like', '%' . $search . '%')
                                ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', '%' . $search . '%'))
                                ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery
                                    ->where('invoice_number', 'like', '%' . $search . '%')
                                    ->orWhere('electronic_number', 'like', '%' . $search . '%')
                                    ->orWhere('cufe', 'like', '%' . $search . '%'));
                        });
                });
            })
            ->oldest('occurred_at');

        $movements = $movementsQuery
            ->paginate(20)
            ->withQueryString();
        $this->attachMovementDisplayData($movements->getCollection(), $session);

        $allMovements = BoxMovement::query()
            ->where('box_session_id', $session->id)
            ->where(fn ($query) => $this->nonVoidedSaleMovementScope($query))
            ->where(fn ($query) => $this->reportableMovementScope($query));
        $physicalIncomeMovements = BoxMovement::query()
            ->where('box_session_id', $session->id)
            ->where(fn ($query) => $this->nonVoidedSaleMovementScope($query))
            ->where('amount', '>', 0);
        $physicalExpenseMovements = BoxMovement::query()
            ->where('box_session_id', $session->id)
            ->where(fn ($query) => $this->nonVoidedSaleMovementScope($query))
            ->where('amount', '<', 0);
        $paymentBreakdown = $this->paymentMethodBreakdown($session);
        $summaryMovements = BoxMovement::query()
            ->with('payment.paymentMethod')
            ->where('box_session_id', $session->id)
            ->where(fn ($query) => $this->nonVoidedSaleMovementScope($query))
            ->where(fn ($query) => $this->reportableMovementScope($query))
            ->get();
        $this->attachMovementDisplayData($summaryMovements, $session);

        return view('cash-management.history-session', [
            'session' => $session,
            'movements' => $movements,
            'filters' => $filters,
            'paymentBreakdown' => $paymentBreakdown,
            'summary' => [
                'movement_count' => (clone $allMovements)->count(),
                'income_total' => money_value((float) (clone $physicalIncomeMovements)->sum('amount')),
                'expense_total' => money_value(abs((float) (clone $physicalExpenseMovements)->sum('amount'))),
                'cash_net_total' => money_value(
                    (float) (clone $physicalIncomeMovements)->sum('amount')
                    - abs((float) (clone $physicalExpenseMovements)->sum('amount'))
                ),
                'expected_cash_total' => money_value(
                    (float) $session->opening_balance
                    + (float) (clone $physicalIncomeMovements)->sum('amount')
                    - abs((float) (clone $physicalExpenseMovements)->sum('amount'))
                ),
                'reported_payment_total' => money_value((float) $paymentBreakdown->sum('total')),
                'transfer_total' => money_value((float) $paymentBreakdown->where('code', 'TRANSFER')->sum('total')),
                'manual_income_total' => money_value((float) $summaryMovements
                    ->where('movement_type', 'manual_income')
                    ->sum(fn (BoxMovement $movement) => abs((float) ($movement->display_amount ?? $movement->amount)))),
                'manual_expense_total' => money_value((float) $summaryMovements
                    ->where('movement_type', 'manual_expense')
                    ->sum(fn (BoxMovement $movement) => abs((float) ($movement->display_amount ?? $movement->amount)))),
            ],
        ]);
    }

    public function printMovementReceipt(BoxMovement $movement)
    {
        if ($response = $this->denyIfUnauthorized(['boxes.view', 'boxes.reports'])) {
            return $response;
        }

        $movement->load(['box', 'session.user', 'user', 'payment.paymentMethod', 'sale.customer', 'sale.invoice', 'sale.tableOrder.table']);
        $this->attachMovementDisplayData(collect([$movement]), $movement->session);

        return view('cash-management.print-movement-receipt', [
            'movement' => $movement,
            'printedAt' => now(),
        ]);
    }

    public function printSessionSummary(BoxSession $session)
    {
        if ($response = $this->denyIfUnauthorized(['boxes.view', 'boxes.reports'])) {
            return $response;
        }

        $session->load(['box', 'user', 'closedBy']);

        $movements = BoxMovement::query()
            ->with(['user', 'payment.paymentMethod', 'sale.customer', 'sale.invoice', 'sale.tableOrder.table'])
            ->where('box_session_id', $session->id)
            ->where(fn ($query) => $this->nonVoidedSaleMovementScope($query))
            ->oldest('occurred_at')
            ->get();
        $this->attachMovementDisplayData($movements, $session);

        $paymentBreakdown = $this->paymentMethodBreakdown($session);
        $physicalIncome = money_value((float) $movements->where('amount', '>', 0)->sum('amount'));
        $physicalExpense = money_value(abs((float) $movements->where('amount', '<', 0)->sum('amount')));

        return view('cash-management.print-session-summary', [
            'session' => $session,
            'movements' => $movements,
            'paymentBreakdown' => $paymentBreakdown,
            'summary' => [
                'opening_balance' => money_value((float) $session->opening_balance),
                'physical_income' => $physicalIncome,
                'physical_expense' => $physicalExpense,
                'expected_balance' => money_value((float) $session->opening_balance + $physicalIncome - $physicalExpense),
                'reported_payment_total' => money_value((float) $paymentBreakdown->sum('total')),
                'transfer_total' => money_value((float) $paymentBreakdown->where('code', 'TRANSFER')->sum('total')),
                'movement_count' => $movements->count(),
            ],
            'printedAt' => now(),
        ]);
    }

    public function monthlyReport(Request $request)
    {
        if ($response = $this->denyIfUnauthorized(['boxes.reports'])) {
            return $response;
        }

        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = $filters['month'] ?? now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $movements = BoxMovement::query()
            ->with(['box', 'session.user'])
            ->whereBetween('occurred_at', [$start, $end])
            ->where(fn ($query) => $this->nonVoidedSaleMovementScope($query))
            ->get();

        $sessions = BoxSession::query()
            ->with(['box', 'user', 'closedBy'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('opened_at', [$start, $end])
                    ->orWhereBetween('closed_at', [$start, $end]);
            })
            ->orderByDesc('opened_at')
            ->get();

        $boxBreakdown = $movements
            ->groupBy(fn (BoxMovement $movement) => $movement->box?->name ?? 'Sin caja')
            ->map(function ($group) {
                return [
                    'income' => money_value((float) $group->where('amount', '>', 0)->sum('amount')),
                    'expense' => money_value(abs((float) $group->where('amount', '<', 0)->sum('amount'))),
                ];
            });

        $openingBase = money_value((float) $sessions->sum('opening_balance'));
        $income = money_value((float) $movements->where('amount', '>', 0)->sum('amount'));
        $expense = money_value(abs((float) $movements->where('amount', '<', 0)->sum('amount')));

        return view('cash-management.monthly', [
            'month' => $month,
            'sessions' => $sessions,
            'boxBreakdown' => $boxBreakdown,
            'summary' => [
                'opening_base' => $openingBase,
                'income' => $income,
                'expense' => $expense,
                'balance' => money_value($openingBase + $income - $expense),
                'closed_sessions' => $sessions->where('status', 'closed')->count(),
            ],
        ]);
    }

    private function boxPermissions(): array
    {
        return ['boxes.view', 'boxes.open', 'boxes.close', 'boxes.movements', 'boxes.reports'];
    }

    private function allowedPaymentMethodRule()
    {
        return Rule::exists('payment_methods', 'id')
            ->where('active', true)
            ->whereIn('code', PaymentMethod::SYSTEM_ALLOWED_CODES);
    }

    private function manualMovementPaymentMethod(mixed $paymentMethodId): PaymentMethod
    {
        if ($paymentMethodId) {
            return PaymentMethod::query()
                ->systemAllowed()
                ->findOrFail($paymentMethodId);
        }

        return PaymentMethod::query()
            ->systemAllowed()
            ->where('code', 'CASH')
            ->firstOrFail();
    }

    private function isCashPaymentMethod(?PaymentMethod $paymentMethod): bool
    {
        return strtoupper((string) ($paymentMethod?->code ?? 'CASH')) === 'CASH';
    }

    private function reportableMovementScope($query): void
    {
        $query->where('amount', '<>', 0)
            ->orWhereNotNull('payment_id')
            ->orWhereIn('movement_type', ['manual_income', 'manual_expense']);
    }

    private function nonVoidedSaleMovementScope($query): void
    {
        $query->whereNull('sale_id')
            ->orWhereHas('sale', function ($saleQuery): void {
                $saleQuery
                    ->where('status', '<>', 'voided')
                    ->whereDoesntHave('invoice', fn ($invoiceQuery) => $invoiceQuery->where('status', 'voided'));
            });
    }

    private function attachMovementDisplayData($movements, ?BoxSession $session): void
    {
        if (! $session || $movements->isEmpty()) {
            return;
        }

        $auditRows = BoxAuditLog::query()
            ->where('box_session_id', $session->id)
            ->get(['metadata']);
        $auditAmountsByMovementId = $this->auditAmountsByMovementId($auditRows);
        $auditPaymentMethodIdsByMovementId = $this->auditPaymentMethodIdsByMovementId($auditRows);
        $auditPaymentMethodsById = PaymentMethod::query()
            ->whereIn('id', $auditPaymentMethodIdsByMovementId->values()->unique()->all())
            ->get()
            ->keyBy('id');

        $movements->each(function (BoxMovement $movement) use ($auditAmountsByMovementId, $auditPaymentMethodIdsByMovementId, $auditPaymentMethodsById): void {
            $auditPaymentMethodId = $auditPaymentMethodIdsByMovementId->get((int) $movement->id);
            $paymentMethod = $movement->payment?->paymentMethod
                ?? ($auditPaymentMethodId ? $auditPaymentMethodsById->get((int) $auditPaymentMethodId) : null);

            $movement->setAttribute('display_amount', $this->movementReportAmount($movement, $auditAmountsByMovementId));
            $movement->setAttribute('display_payment_method', $paymentMethod?->name ?? 'Sin metodo');
        });
    }

    private function movementReportAmount(BoxMovement $movement, $auditAmountsByMovementId): float
    {
        $paymentReportAmount = max(0, (float) ($movement->payment?->received_amount ?? 0) - (float) ($movement->payment?->change_amount ?? 0));

        if ($paymentReportAmount > 0) {
            return money_value($paymentReportAmount);
        }

        $auditAmount = $auditAmountsByMovementId->get((int) $movement->id);

        if ($auditAmount !== null) {
            $signedAuditAmount = $movement->movement_type === 'manual_expense' || (float) $movement->amount < 0
                ? -1 * abs((float) $auditAmount)
                : abs((float) $auditAmount);

            return money_value($signedAuditAmount);
        }

        return money_value((float) $movement->amount);
    }

    private function auditAmountsByMovementId($auditRows)
    {
        return $auditRows->mapWithKeys(function (BoxAuditLog $auditLog): array {
            $movementId = $auditLog->metadata['movement_id'] ?? null;

            if (! $movementId || ! array_key_exists('amount', $auditLog->metadata ?? [])) {
                return [];
            }

            return [(int) $movementId => money_value((float) $auditLog->metadata['amount'])];
        });
    }

    private function auditPaymentMethodIdsByMovementId($auditRows)
    {
        return $auditRows->mapWithKeys(function (BoxAuditLog $auditLog): array {
            $movementId = $auditLog->metadata['movement_id'] ?? null;
            $paymentMethodId = $auditLog->metadata['payment_method_id'] ?? null;

            if (! $movementId || ! $paymentMethodId) {
                return [];
            }

            return [(int) $movementId => (int) $paymentMethodId];
        });
    }

    private function attachHistoryTransferTotals($sessions): void
    {
        if ($sessions->isEmpty()) {
            return;
        }

        $sessionIds = $sessions->pluck('id')->all();
        $transferMethodIds = PaymentMethod::query()
            ->where('code', 'TRANSFER')
            ->pluck('id')
            ->all();

        if ($transferMethodIds === []) {
            $sessions->each(fn (BoxSession $session) => $session->setAttribute('transfer_total', 0));

            return;
        }

        $transferTotals = BoxMovement::query()
            ->with('payment')
            ->whereIn('box_session_id', $sessionIds)
            ->where(fn ($query) => $this->nonVoidedSaleMovementScope($query))
            ->whereHas('payment', fn ($query) => $query->whereIn('payment_method_id', $transferMethodIds))
            ->get()
            ->groupBy('box_session_id')
            ->map(fn ($movements) => money_value((float) $movements->sum(
                fn (BoxMovement $movement) => max(0, (float) ($movement->payment?->received_amount ?? 0) - (float) ($movement->payment?->change_amount ?? 0))
            )));

        $manualTransferMovements = BoxMovement::query()
            ->whereIn('box_session_id', $sessionIds)
            ->whereNull('payment_id')
            ->where(fn ($query) => $this->nonVoidedSaleMovementScope($query))
            ->get(['id', 'box_session_id'])
            ->keyBy('id');
        $manualTransferTotals = BoxAuditLog::query()
            ->whereIn('box_session_id', $sessionIds)
            ->get(['metadata'])
            ->map(function (BoxAuditLog $auditLog) use ($transferMethodIds, $manualTransferMovements): ?array {
                $movementId = (int) ($auditLog->metadata['movement_id'] ?? 0);
                $paymentMethodId = (int) ($auditLog->metadata['payment_method_id'] ?? 0);

                if (! $movementId || ! in_array($paymentMethodId, $transferMethodIds, true) || ! $manualTransferMovements->has($movementId)) {
                    return null;
                }

                return [
                    'session_id' => $manualTransferMovements->get($movementId)->box_session_id,
                    'amount' => money_value((float) ($auditLog->metadata['amount'] ?? 0)),
                ];
            })
            ->filter()
            ->groupBy('session_id')
            ->map(fn ($rows) => money_value((float) $rows->sum('amount')));

        $sessions->each(function (BoxSession $session) use ($transferTotals, $manualTransferTotals): void {
            $session->setAttribute('transfer_total', money_value(
                (float) ($transferTotals->get($session->id) ?? 0)
                + (float) ($manualTransferTotals->get($session->id) ?? 0)
            ));
        });
    }

    private function paymentMethodBreakdown(?BoxSession $session)
    {
        if (! $session) {
            return collect();
        }

        $auditRows = BoxAuditLog::query()
            ->where('box_session_id', $session->id)
            ->get(['metadata']);

        $auditAmountsByMovementId = $this->auditAmountsByMovementId($auditRows);
        $auditPaymentMethodIdsByMovementId = $this->auditPaymentMethodIdsByMovementId($auditRows);
        $auditPaymentMethodsById = PaymentMethod::query()
            ->whereIn('id', $auditPaymentMethodIdsByMovementId->values()->unique()->all())
            ->get()
            ->keyBy('id');

        return BoxMovement::query()
            ->with('payment.paymentMethod')
            ->where('box_session_id', $session->id)
            ->where(fn ($query) => $this->nonVoidedSaleMovementScope($query))
            ->where(function ($query) use ($auditPaymentMethodIdsByMovementId): void {
                $query->whereNotNull('payment_id')
                    ->orWhereIn('id', $auditPaymentMethodIdsByMovementId->keys()->all());
            })
            ->get()
            ->map(function (BoxMovement $movement) use ($auditAmountsByMovementId, $auditPaymentMethodIdsByMovementId, $auditPaymentMethodsById): array {
                $auditPaymentMethodId = $auditPaymentMethodIdsByMovementId->get((int) $movement->id);
                $paymentMethod = $movement->payment?->paymentMethod
                    ?? ($auditPaymentMethodId ? $auditPaymentMethodsById->get((int) $auditPaymentMethodId) : null);
                $paymentName = $paymentMethod?->name ?? 'Sin metodo';
                $paymentCode = strtoupper((string) ($paymentMethod?->code ?? ''));
                $paymentReportAmount = max(0, (float) ($movement->payment?->received_amount ?? 0) - (float) ($movement->payment?->change_amount ?? 0));
                $auditReportAmount = $auditAmountsByMovementId->get((int) $movement->id);
                $reportAmount = (float) $movement->amount > 0
                    ? money_value((float) $movement->amount)
                    : money_value((float) ($paymentReportAmount > 0 ? $paymentReportAmount : ($auditReportAmount ?? 0)));

                return [
                    'name' => $paymentName,
                    'code' => $paymentCode,
                    'total' => $reportAmount,
                    'box_impact' => money_value((float) $movement->amount),
                    'count' => 1,
                    'affects_box' => $paymentCode === 'CASH',
                ];
            })
            ->filter(fn (array $row): bool => (float) $row['total'] > 0 || (float) $row['box_impact'] !== 0.0)
            ->groupBy('name')
            ->map(function ($rows, string $name): array {
                return [
                    'name' => $name,
                    'code' => $rows->first()['code'],
                    'total' => money_value((float) $rows->sum('total')),
                    'box_impact' => money_value((float) $rows->sum('box_impact')),
                    'count' => (int) $rows->sum('count'),
                    'affects_box' => (bool) $rows->first()['affects_box'],
                ];
            })
            ->sortBy('name')
            ->values();
    }

    private function denyIfUnauthorizedForCatalog(): ?Response
    {
        $user = auth()->user();

        if ($user && $user->hasRole('Admin')) {
            return null;
        }

        return response()->view('errors.403', [], 403);
    }

    private function denyIfUnauthorized(array $permissions): ?Response
    {
        if ($this->canAccess($permissions)) {
            return null;
        }

        return response()->view('errors.403', [], 403);
    }

    private function canAccess(array $permissions): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole('Admin') || $user->hasRole('Cajero') || $user->hasAnyPermission($permissions));
    }

    private function logAudit(Box $box, BoxSession $session, string $action, string $description, array $metadata = []): void
    {
        BoxAuditLog::query()->create([
            'box_id' => $box->id,
            'box_session_id' => $session->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    private function validateBoxData(Request $request, ?Box $box = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'description' => trim((string) $request->input('description')),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function generateUniqueBoxCode(string $name, ?int $ignoreBoxId = null): string
    {
        $normalized = Str::upper(Str::slug($name, '-'));
        $base = 'BOX-' . ($normalized !== '' ? substr($normalized, 0, 40) : 'CAJA');
        $candidate = $base;
        $suffix = 2;

        while (
            Box::query()
                ->when($ignoreBoxId, fn ($query) => $query->whereKeyNot($ignoreBoxId))
                ->where('code', $candidate)
                ->exists()
        ) {
            $candidate = substr($base, 0, 46) . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
