<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        return view('cash-management.show', [
            'box' => $box,
            'currentSession' => $currentSession,
            'incomeTotal' => $currentSession ? $currentSession->incomeTotal() : 0,
            'expenseTotal' => $currentSession ? $currentSession->expenseTotal() : 0,
            'currentBalance' => $currentSession ? $currentSession->currentBalance() : 0,
            'automaticIncome' => $automaticIncome,
            'manualIncome' => $manualIncome,
            'manualExpense' => $manualExpense,
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
            $signedAmount = $validated['movement_type'] === 'manual_expense'
                ? -1 * $rawAmount
                : $rawAmount;

            $balanceBefore = $session->currentBalance();
            $balanceAfter = money_value($balanceBefore + $signedAmount);

            $movement = $session->movements()->create([
                'box_id' => $box->id,
                'box_session_id' => $session->id,
                'user_id' => Auth::id(),
                'movement_type' => $validated['movement_type'],
                'amount' => $signedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $validated['description'],
                'occurred_at' => now(),
            ]);

            $this->logAudit(
                $box,
                $session,
                $validated['movement_type'],
                $validated['description'],
                [
                    'movement_id' => $movement->id,
                    'amount' => (float) $signedAmount,
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
            ->withCount('movements')
            ->where('status', 'closed')
            ->when($filters['date_from'] ?? null, fn ($query, string $dateFrom) => $query->whereDate('closed_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, string $dateTo) => $query->whereDate('closed_at', '<=', $dateTo))
            ->when($filters['user_id'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['box_id'] ?? null, fn ($query, int $boxId) => $query->where('box_id', $boxId))
            ->latest('closed_at');

        $sessions = (clone $sessionsQuery)
            ->paginate(20)
            ->withQueryString();

        $sessionTotals = (clone $sessionsQuery)->get()
            ->reduce(function (array $totals, BoxSession $session): array {
                $turn = $this->sessionTurnLabel($session);
                $totals[$turn] = ($totals[$turn] ?? 0) + 1;

                return $totals;
            }, []);

        return view('cash-management.history', [
            'sessions' => $sessions,
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'boxes' => Box::query()->orderBy('name')->get(['id', 'name']),
            'summary' => [
                'closures' => (clone $sessionsQuery)->count(),
                'morning' => $sessionTotals['Cierre de la mañana'] ?? 0,
                'afternoon' => $sessionTotals['Cierre de la tarde'] ?? 0,
                'night' => $sessionTotals['Cierre de la noche'] ?? 0,
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
        ]);

        $session->load(['box', 'user', 'closedBy']);

        $movementsQuery = BoxMovement::query()
            ->with([
                'user',
                'sale.customer',
                'sale.invoice',
                'sale.tableOrder.table',
                'sale.items',
            ])
            ->where('box_session_id', $session->id)
            ->where('amount', '>', 0)
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

        $allIncomeMovements = BoxMovement::query()
            ->where('box_session_id', $session->id)
            ->where('amount', '>', 0);

        return view('cash-management.history-session', [
            'session' => $session,
            'movements' => $movements,
            'filters' => $filters,
            'turnLabel' => $this->sessionTurnLabel($session),
            'summary' => [
                'income_movements' => (clone $allIncomeMovements)->count(),
                'income_total' => money_value((float) (clone $allIncomeMovements)->sum('amount')),
                'sales_income' => money_value((float) (clone $allIncomeMovements)->whereNotNull('sale_id')->sum('amount')),
                'manual_income' => money_value((float) (clone $allIncomeMovements)->whereNull('sale_id')->sum('amount')),
            ],
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

    private function sessionTurnLabel(BoxSession $session): string
    {
        $hour = (int) ($session->closed_at ?? $session->opened_at ?? now())->format('H');

        return match (true) {
            $hour < 12 => 'Cierre de la mañana',
            $hour < 18 => 'Cierre de la tarde',
            default => 'Cierre de la noche',
        };
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
