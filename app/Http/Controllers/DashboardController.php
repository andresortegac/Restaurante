<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\TableOrder;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user()->loadMissing('roles.permissions');
        $monthStart = now()->startOfMonth();

        return view('dashboard', [
            'accountUser' => $user,
            'roles' => $user->roles,
            'permissionsCount' => $user->roles
                ->flatMap(fn ($role) => $role->permissions->pluck('id'))
                ->unique()
                ->count(),
            'stats' => [
                'orders_today' => TableOrder::query()->whereDate('created_at', today())->count(),
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
