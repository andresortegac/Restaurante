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
            'code' => strtoupper($validated['code']),
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

        $movements = BoxMovement::query()
            ->with(['user', 'sale', 'payment.paymentMethod'])
            ->where('box_id', $box->id)
            ->when($currentSession, fn ($query) => $query->where('box_session_id', $currentSession->id))
            ->latest('occurred_at')
            ->paginate(15);

        $recentSessions = $box->sessions()
            ->with(['user', 'closedBy'])
            ->latest('opened_at')
            ->limit(5)
            ->get();

        $automaticIncome = $currentSession
            ? (float) $currentSession->movements()
                ->whereIn('movement_type', ['sale_income', 'table_order_payment'])
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
            'recentSessions' => $recentSessions,
            'movements' => $movements,
            'incomeTotal' => $currentSession ? $currentSession->incomeTotal() : 0,
            'expenseTotal' => $currentSession ? $currentSession->expenseTotal() : 0,
            'currentBalance' => $currentSession ? $currentSession->currentBalance() : 0,
            'automaticIncome' => $automaticIncome,
            'manualIncome' => $manualIncome,
            'manualExpense' => $manualExpense,
        ]);
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
            'code' => strtoupper($validated['code']),
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
                'opening_balance' => round((float) $validated['opening_balance'], 2),
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
                'Apertura de caja con base inicial de $' . number_format($session->opening_balance, 2),
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

            $rawAmount = round((float) $validated['amount'], 2);
            $signedAmount = $validated['movement_type'] === 'manual_expense'
                ? -1 * $rawAmount
                : $rawAmount;

            $balanceBefore = $session->currentBalance();
            $balanceAfter = round($balanceBefore + $signedAmount, 2);

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
            $countedBalance = round((float) $validated['counted_balance'], 2);
            $difference = round($countedBalance - $expectedBalance, 2);

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
            'action' => ['nullable', 'string', 'max:50'],
        ]);

        $logsQuery = BoxAuditLog::query()
            ->with(['box', 'session.user', 'user'])
            ->when($filters['date_from'] ?? null, fn ($query, string $dateFrom) => $query->whereDate('occurred_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, string $dateTo) => $query->whereDate('occurred_at', '<=', $dateTo))
            ->when($filters['user_id'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
            ->latest('occurred_at');

        $logs = (clone $logsQuery)
            ->paginate(20)
            ->withQueryString();

        $actionLabels = [
            'box_opened' => 'Apertura de caja',
            'box_closed' => 'Cierre de caja',
            'manual_income' => 'Ingreso manual',
            'manual_expense' => 'Egreso manual',
            'sale_income' => 'Venta POS',
            'table_order_payment' => 'Cobro de mesa',
        ];

        return view('cash-management.history', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'actions' => [
                'box_opened',
                'box_closed',
                'manual_income',
                'manual_expense',
                'sale_income',
                'table_order_payment',
            ],
            'actionLabels' => $actionLabels,
            'summary' => [
                'total' => (clone $logsQuery)->count(),
                'openings' => (clone $logsQuery)->where('action', 'box_opened')->count(),
                'closings' => (clone $logsQuery)->where('action', 'box_closed')->count(),
                'adjustments' => (clone $logsQuery)->whereIn('action', ['manual_income', 'manual_expense'])->count(),
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
                    'income' => round((float) $group->where('amount', '>', 0)->sum('amount'), 2),
                    'expense' => round(abs((float) $group->where('amount', '<', 0)->sum('amount')), 2),
                ];
            });

        $openingBase = round((float) $sessions->sum('opening_balance'), 2);
        $income = round((float) $movements->where('amount', '>', 0)->sum('amount'), 2);
        $expense = round(abs((float) $movements->where('amount', '<', 0)->sum('amount')), 2);

        return view('cash-management.monthly', [
            'month' => $month,
            'sessions' => $sessions,
            'boxBreakdown' => $boxBreakdown,
            'summary' => [
                'opening_base' => $openingBase,
                'income' => $income,
                'expense' => $expense,
                'balance' => round($openingBase + $income - $expense, 2),
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
        $user = auth()->user();

        if ($user && ($user->hasRole('Admin') || $user->hasRole('Cajero') || $user->hasAnyPermission($permissions))) {
            return null;
        }

        return response()->view('errors.403', [], 403);
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
            'code' => strtoupper(trim((string) $request->input('code'))),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:boxes,code' . ($box ? ',' . $box->id : '')],
        ]);
    }
}
