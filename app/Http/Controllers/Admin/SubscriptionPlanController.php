<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $allPlans = SubscriptionPlan::all();

        if ($allPlans->isEmpty()) {
            return ApiResponse::failure('No plan found.');       
        }

        return ApiResponse::success('All plans retrieved successfully.', [
            'subscription_plans' => $allPlans,
        ]);       
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'unique:subscription_plans,name'],
            'price' => ['required', 'numeric'],
            'allowed_subjects_ids' => ['nullable', 'array'],
            'allowed_subjects_ids.*' => ['numeric', 'exists:subjects,id'],
            'allowed_number_of_questions' => ['nullable', 'integer'],
            'allowed_number_of_attempts' => ['nullable', 'integer']
        ]);

        $subscription = SubscriptionPlan::query()->create($validated);
        if ($subscription) {
            return ApiResponse::success('Subscription plan created', [
                'subscription_plan' => $subscription->toArray()
            ]);
        }

        return ApiResponse::failure('Subscription plan creation failed');
    }

    public function update(Request $request, $uuid)
    {
        $subscription_plan = SubscriptionPlan::query()->firstWhere('uuid', $uuid);
        if (!$subscription_plan) {
            return ApiResponse::failure('Subscription plan not found', statusCode: 404);
        }

        $validated = $request->validate([
            'name' => [
                'nullable',
                Rule::unique('subscription_plans', 'name')->ignore($uuid)
            ],
            'price' => ['nullable', 'numeric'],
            'allowed_subjects_ids' => ['nullable', 'array'],
            'allowed_subjects_ids.*' => ['numeric', 'exists:subjects,id'],
            'allowed_number_of_questions' => ['nullable', 'integer'],
            'allowed_number_of_attempts' => ['nullable', 'integer']
        ]);

        $updated = $subscription_plan->update($validated);
        if (!$updated){
            return ApiResponse::failure('Update failed');
        }
        return ApiResponse::success("Updated successfully", [
            'subscription_plan' => $subscription_plan
        ]);
    }

    public function show($uuid)
    {
        $subscription_plan = SubscriptionPlan::query()->firstWhere('uuid', $uuid);
        if (!$subscription_plan) {
            return ApiResponse::failure('Subscription plan not found', statusCode: 404);
        }

        return ApiResponse::success("Subscription plan fetched", [
            'subscription_plan' => $subscription_plan
        ]);
    }

    public function delete($uuid)
    {
        $subscription_plan = SubscriptionPlan::query()->firstWhere('uuid', $uuid);
        if (!$subscription_plan) {
            return ApiResponse::failure('Subscription plan not found', statusCode: 404);
        }

       $deleted = $subscription_plan->delete();

        if (!$deleted){
            return ApiResponse::failure('Delete failed');
        }
        return ApiResponse::success("Delete successfully");
    }
}
