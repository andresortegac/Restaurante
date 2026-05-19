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
        $now = now();
        $todayStart = today();
        $monthStart = $now->copy()->startOfMonth();
        $canViewFinancialStats = $user->hasRole(['Admin', 'Administrador', 'admin', 'administrador']);

        $stats = [
            'table_orders_today' => TableOrder::query()->where('created_at', '>=', $todayStart)->count(),
            'deliveries_today' => Delivery::query()->where('created_at', '>=', $todayStart)->count(),
            'reservations_today' => Reservation::query()->whereBetween('reservation_at', [$todayStart, $todayStart->copy()->endOfDay()])->count(),
            'occupied_tables' => RestaurantTable::query()->active()->where('status', 'occupied')->count(),
            'available_tables' => RestaurantTable::query()->active()->where('status', 'free')->count(),
            'sales_today' => 0.0,
            'monthly_income' => 0.0,
            'customers' => Customer::query()->where('is_active', true)->count(),
        ];

        if ($canViewFinancialStats) {
            $stats['sales_today'] = (float) Sale::query()
                ->where('created_at', '>=', $todayStart)
                ->sum('total');
            $stats['monthly_income'] = (float) Sale::query()
                ->whereBetween('created_at', [$monthStart, $now])
                ->sum('total');
        }

        return view('dashboard', [
            'accountUser' => $user,
            'roles' => $user->roles,
            'canViewFinancialStats' => $canViewFinancialStats,
            'permissionsCount' => $user->roles
                ->flatMap(fn ($role) => $role->permissions->pluck('id'))
                ->unique()
                ->count(),
            'stats' => $stats,
        ]);
    }
}
