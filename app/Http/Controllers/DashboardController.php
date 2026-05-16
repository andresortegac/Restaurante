<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\RestaurantTable;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\TableOrder;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user()->loadMissing('roles.permissions');
        $monthStart = now()->startOfMonth();
        $canViewFinancialStats = $user->hasRole(['Admin', 'Administrador', 'admin', 'administrador']);

        return view('dashboard', [
            'accountUser' => $user,
            'roles' => $user->roles,
            'canViewFinancialStats' => $canViewFinancialStats,
            'permissionsCount' => $user->roles
                ->flatMap(fn ($role) => $role->permissions->pluck('id'))
                ->unique()
                ->count(),
            'stats' => [
                'table_orders_today' => TableOrder::query()->whereDate('created_at', today())->count(),
                'deliveries_today' => Delivery::query()->whereDate('created_at', today())->count(),
                'reservations_today' => Reservation::query()->whereDate('reservation_at', today())->count(),
                'occupied_tables' => RestaurantTable::query()->active()->where('status', 'occupied')->count(),
                'available_tables' => RestaurantTable::query()->active()->where('status', 'free')->count(),
                'sales_today' => (float) Sale::query()->whereDate('created_at', today())->sum('total'),
                'monthly_income' => (float) Sale::query()
                    ->whereBetween('created_at', [$monthStart, now()])
                    ->sum('total'),
                'customers' => Customer::query()->where('is_active', true)->count(),
            ],
        ]);
    }
}
