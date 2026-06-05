<?php

namespace App\Http\Controllers;

use App\Models\Atem;
use App\Models\AtemAttachment;
use App\Services\AtemAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AtemAttachmentController extends Controller
{
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
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $request->validate([
            'file' => 'required|file|max:' . self::MAX_KILOBYTES,
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, explode(',', self::ALLOWED_MIMES), true)) {
            return response()->json([
                'success' => false,
                'message' => 'File type not allowed: .' . $ext,
            ], 422);
        }

        $uploadedBy = $request->input('uploaded_by');

        AtemAttachment::create([
            'atem_id'     => $atem->id,
            'name'        => $file->getClientOriginalName(),
            'type'        => $file->getClientMimeType(),
            'size'        => $file->getSize(),
            'content'     => base64_encode(file_get_contents($file->getRealPath())),
            'uploaded_by' => $uploadedBy,
        ]);

        AtemAuditLogger::log(
            $atem->id,
            'attachment_added',
            $uploadedBy ? (int) $uploadedBy : null,
            'Uploaded ' . $file->getClientOriginalName() . ' (' . $file->getSize() . ' bytes).'
        );

        return response()->json([
            'success' => true,
            'data'    => $this->attachments($atem->id),
        ]);
    }

    /**
     * DELETE /api/atem/{id}/attachments/{attId}
     */
    public function destroy(Request $request, int $id, int $attId): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $att = AtemAttachment::where('atem_id', $atem->id)->where('id', $attId)->first();
        $attName = $att ? $att->name : '#' . $attId;

        AtemAttachment::where('atem_id', $atem->id)->where('id', $attId)->delete();

        $actorId = $request->input('actor_id');
        AtemAuditLogger::log(
            $atem->id,
            'attachment_removed',
            $actorId ? (int) $actorId : null,
            'Removed attachment: ' . $attName . '.'
        );

        return response()->json([
            'success' => true,
            'data'    => $this->attachments($atem->id),
        ]);
    }

    /**
     * GET /api/atem/{id}/attachments/{attId}/download
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

    private function attachments(int $atemId)
    {
        return AtemAttachment::where('atem_id', $atemId)
            ->orderBy('id')
            ->get(['id', 'atem_id', 'name', 'type', 'size', 'created_at']);
    }
}
