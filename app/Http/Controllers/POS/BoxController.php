<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BoxController extends Controller
{
    public function index()
    {
        return response()->json(
            Box::query()->with('activeSession')->orderBy('name')->get()
        );
    }

    public function open(Request $request, $id)
    {
        $box = Box::findOrFail($id);
        $validated = $request->validate([
            'opening_balance' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($box, $validated) {
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
                'status' => 'open',
                'opened_at' => now(),
            ]);

            $lockedBox->update([
                'user_id' => Auth::id(),
                'closed_by_user_id' => null,
                'opening_balance' => $session->opening_balance,
                'status' => 'open',
                'closing_balance' => null,
                'counted_balance' => null,
                'difference_amount' => null,
                'closing_notes' => null,
                'opened_at' => $session->opened_at,
                'closed_at' => null,
            ]);

            BoxAuditLog::query()->create([
                'box_id' => $lockedBox->id,
                'box_session_id' => $session->id,
                'user_id' => Auth::id(),
                'action' => 'box_opened',
                'description' => 'Apertura realizada desde POS API.',
                'metadata' => ['opening_balance' => (float) $session->opening_balance],
                'occurred_at' => now(),
            ]);

            return response()->json($lockedBox->load('activeSession'));
        });
    }

    public function close(Request $request, $id)
    {
        $box = Box::findOrFail($id);
        $validated = $request->validate([
            'closing_balance' => 'nullable|numeric|min:0',
            'counted_balance' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($box, $validated) {
            $lockedBox = Box::query()->lockForUpdate()->findOrFail($box->id);
            $session = BoxSession::query()
                ->where('box_id', $lockedBox->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw ValidationException::withMessages([
                    'counted_balance' => 'La caja ya esta cerrada.',
                ]);
            }

            $expectedBalance = $session->currentBalance();
            $countedBalance = round((float) ($validated['counted_balance'] ?? $validated['closing_balance'] ?? 0), 2);
            $difference = round($countedBalance - $expectedBalance, 2);

            $session->update([
                'status' => 'closed',
                'counted_balance' => $countedBalance,
                'difference_amount' => $difference,
                'closed_by_user_id' => Auth::id(),
                'closed_at' => now(),
            ]);

            $lockedBox->update([
                'status' => 'closed',
                'closing_balance' => $expectedBalance,
                'counted_balance' => $countedBalance,
                'difference_amount' => $difference,
                'closed_by_user_id' => Auth::id(),
                'closed_at' => $session->closed_at,
            ]);

            BoxAuditLog::query()->create([
                'box_id' => $lockedBox->id,
                'box_session_id' => $session->id,
                'user_id' => Auth::id(),
                'action' => 'box_closed',
                'description' => 'Cierre realizado desde POS API.',
                'metadata' => [
                    'expected_balance' => $expectedBalance,
                    'counted_balance' => $countedBalance,
                    'difference_amount' => $difference,
                ],
                'occurred_at' => now(),
            ]);

            return response()->json($lockedBox->fresh('activeSession'));
        });
    }
}
