<?php

namespace App\Http\Controllers;

use App\Models\Atem;
use App\Models\AtemReferenceLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtemReferenceLinkController extends Controller
{
    /**
     * GET /api/atem/{id}/reference-links
     * Lists the reference links for a card.
     */
    public function index(int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->links($atem->id),
        ]);
    }

    /**
     * POST /api/atem/{id}/reference-links
     * Adds a single named reference link.
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'url'      => 'required|url|max:1000',
            'added_by' => 'nullable|integer',
        ]);

        AtemReferenceLink::create([
            'atem_id'  => $atem->id,
            'name'     => $data['name'],
            'url'      => $data['url'],
            'added_by' => $data['added_by'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->links($atem->id),
        ]);
    }

    /**
     * DELETE /api/atem/{id}/reference-links/{linkId}
     * Removes one reference link scoped to the card.
     */
    public function destroy(int $id, int $linkId): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        AtemReferenceLink::where('atem_id', $atem->id)->where('id', $linkId)->delete();

        return response()->json([
            'success' => true,
            'data'    => $this->links($atem->id),
        ]);
    }

    /**
     * Returns the card's reference links ordered for display.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function links(int $atemId)
    {
        return AtemReferenceLink::where('atem_id', $atemId)->orderBy('id')->get();
    }
}
