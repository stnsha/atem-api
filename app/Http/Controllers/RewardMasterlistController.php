<?php

namespace App\Http\Controllers;

use App\Models\RewardMasterlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardMasterlistController extends Controller
{
    /**
     * GET /api/reward-masterlist
     * Returns every entry (active and inactive) - the admin masterlist page
     * needs to see and toggle inactive entries too. Active-only entries for
     * the create-form dropdown are served separately via AtemController::lookups().
     */
    public function index(): JsonResponse
    {
        $entries = RewardMasterlist::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => $entries,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reward_value' => 'required|string|max:255',
            'is_active'    => 'nullable|boolean',
        ]);

        $entry = RewardMasterlist::create([
            'reward_value' => $data['reward_value'],
            'is_active'    => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $entry,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $entry = RewardMasterlist::findOrFail($id);

        $data = $request->validate([
            'reward_value' => 'sometimes|string|max:255',
            'is_active'    => 'sometimes|boolean',
        ]);

        $entry->update($data);

        return response()->json([
            'success' => true,
            'data'    => $entry,
        ]);
    }
}
