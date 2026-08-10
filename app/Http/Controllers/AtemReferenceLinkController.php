<?php

namespace App\Http\Controllers;

use App\Models\Atem;
use App\Models\AtemReferenceLink;
use App\Services\AtemAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtemReferenceLinkController extends Controller
{
    /**
     * GET /api/atem/{id}/reference-links
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

        AtemAuditLogger::log(
            $atem->id,
            'reflink_added',
            $data['added_by'] ?? null,
            'Added reference link: ' . $data['name'] . '.'
        );

        return response()->json([
            'success' => true,
            'data'    => $this->links($atem->id),
        ]);
    }

    /**
     * PATCH /api/atem/{id}/reference-links/{linkId}
     * Toggles whether this reference link is marked as the reference outcome.
     */
    public function update(Request $request, int $id, int $linkId): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $request->validate([
            'is_reference_outcome' => 'required|boolean',
        ]);

        $link = AtemReferenceLink::where('atem_id', $atem->id)->where('id', $linkId)->firstOrFail();
        $link->is_reference_outcome = $request->boolean('is_reference_outcome');
        $link->save();

        $actorId = $request->input('actor_id');
        AtemAuditLogger::log(
            $atem->id,
            $link->is_reference_outcome ? 'reflink_marked_reference_outcome' : 'reflink_unmarked_reference_outcome',
            $actorId ? (int) $actorId : null,
            ($link->is_reference_outcome ? 'Marked ' : 'Unmarked ') . $link->name . ' as reference outcome.'
        );

        return response()->json([
            'success' => true,
            'data'    => $this->links($atem->id),
        ]);
    }

    /**
     * DELETE /api/atem/{id}/reference-links/{linkId}
     */
    public function destroy(Request $request, int $id, int $linkId): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $link = AtemReferenceLink::where('atem_id', $atem->id)->where('id', $linkId)->first();
        $linkName = $link ? $link->name : '#' . $linkId;

        AtemReferenceLink::where('atem_id', $atem->id)->where('id', $linkId)->delete();

        $actorId = $request->input('actor_id');
        AtemAuditLogger::log(
            $atem->id,
            'reflink_removed',
            $actorId ? (int) $actorId : null,
            'Removed reference link: ' . $linkName . '.'
        );

        return response()->json([
            'success' => true,
            'data'    => $this->links($atem->id),
        ]);
    }

    private function links(int $atemId)
    {
        return AtemReferenceLink::where('atem_id', $atemId)->orderBy('id')->get();
    }
}
