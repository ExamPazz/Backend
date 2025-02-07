<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       SubscriptionPlan::query()->create(
           [
               'name' => 'Freemium',
               'price' => 0,
               'allowed_number_of_questions' => 5,
               'allowed_number_of_attempts' => 1
           ]);

       SubscriptionPlan::query()->create(
           [
               'name' => 'Standard',
               'price' => 5000
           ]);
    }
}
