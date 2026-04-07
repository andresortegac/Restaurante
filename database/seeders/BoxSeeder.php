<?php

namespace Database\Seeders;

use App\Models\Box;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoxSeeder extends Seeder
{
    public function run(): void
    {
        $boxes = [
            ['name' => 'Caja 1', 'code' => 'BOX-001', 'user_id' => 1, 'opening_balance' => 500, 'status' => 'open', 'opened_at' => now()],
            ['name' => 'Caja 2', 'code' => 'BOX-002', 'user_id' => null, 'opening_balance' => 0, 'status' => 'closed'],
            ['name' => 'Caja 3', 'code' => 'BOX-003', 'user_id' => null, 'opening_balance' => 0, 'status' => 'closed'],
        ];

        foreach ($boxes as $box) {
            Box::create($box);
        }
    }
}
