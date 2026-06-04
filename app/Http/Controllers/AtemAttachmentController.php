<?php

namespace App\Http\Controllers;

use App\Models\Atem;
use App\Models\AtemAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AtemAttachmentController extends Controller
{
    // 10 MB, expressed in kilobytes for the max validation rule.
    private const MAX_KILOBYTES = 10240;

    private const ALLOWED_MIMES = 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt';

    /**
     * GET /api/atem/{id}/attachments
     */
    public function index(int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->attachments($atem->id),
        ]);
    }

    /**
     * POST /api/atem/{id}/attachments
     * Stores one uploaded file as base64 in the DB and records it against the card.
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $request->validate([
            'file' => 'required|file|max:' . self::MAX_KILOBYTES,
        ]);

        // Validate by extension. Content-sniffing (the mimes rule) wrongly
        // rejects valid zip-based Office files such as docx/xlsx.
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, explode(',', self::ALLOWED_MIMES), true)) {
            return response()->json([
                'success' => false,
                'message' => 'File type not allowed: .' . $ext,
            ], 422);
        }

        AtemAttachment::create([
            'atem_id'     => $atem->id,
            'name'        => $file->getClientOriginalName(),
            'type'        => $file->getClientMimeType(),
            'size'        => $file->getSize(),
            'content'     => base64_encode(file_get_contents($file->getRealPath())),
            'uploaded_by' => $request->input('uploaded_by'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->attachments($atem->id),
        ]);
    }

    /**
     * DELETE /api/atem/{id}/attachments/{attId}
     * Removes the DB record (no file system involved).
     */
    public function destroy(int $id, int $attId): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        AtemAttachment::where('atem_id', $atem->id)->where('id', $attId)->delete();

        return response()->json([
            'success' => true,
            'data'    => $this->attachments($atem->id),
        ]);
    }

    /**
     * GET /api/atem/{id}/attachments/{attId}/download
     * Decodes the stored base64 and returns the original bytes (relayed by the
     * odb proxy). The byte content is identical to what was uploaded.
     */
    public function download(int $id, int $attId): Response
    {
        $atem = Atem::findOrFail($id);

        $attachment = AtemAttachment::where('atem_id', $atem->id)->where('id', $attId)->firstOrFail();

        $bytes = base64_decode($attachment->content);

        return response($bytes, 200)
            ->header('Content-Type', $attachment->type ?: 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="' . addslashes($attachment->name) . '"')
            ->header('Content-Length', strlen($bytes));
    }

    /**
     * Returns the card's attachments ordered for display. The base64 content
     * column is deliberately excluded from the listing payload.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function attachments(int $atemId)
    {
        return AtemAttachment::where('atem_id', $atemId)
            ->orderBy('id')
            ->get(['id', 'atem_id', 'name', 'type', 'size', 'created_at']);
    }
}
