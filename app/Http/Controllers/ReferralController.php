<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Referral;
use App\Support\ApiResponse;

class ReferralController extends Controller
{
    public function referralStats(Request $request)
    {
        $user = $request->user();

        $referrals = Referral::where('referrer_id', $user->id)->with('referred')->get();
        $completed = $referrals->where('status', 'completed')->count();
        $pending = $referrals->where('status', 'pending')->count();

        return ApiResponse::success('Referral stats retrieved successfully.', [
            'total_referrals' => $referrals->count(),
            'completed' => $completed,
            'pending' => $pending,
            'wallet_balance' => $user->wallet?->balance ?? 0,
            'referrals' => $referrals,
        ]);       
    }

}
