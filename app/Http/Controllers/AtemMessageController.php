<?php

namespace App\Http\Controllers;

use App\Models\Atem;
use App\Models\AtemMessage;
use App\Models\AtemNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AtemMessageController extends Controller
{
    /** Window during which the sender may edit or unsend their own message. */
    private const EDIT_WINDOW_SECONDS = 60;

    /**
     * GET /api/atem/{id}/messages
     * Returns the full thread (soft-deleted/unsent messages excluded by default
     * scope). The frontend does a full resync on each poll rather than an
     * incremental fetch, so edits/unsends on existing messages are picked up too.
     */
    public function index(int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $query = AtemMessage::where('atem_id', $atem->id)
            ->orderBy('created_at')
            ->orderBy('id');

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ]);
    }

    /**
     * POST /api/atem/{id}/messages
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $data = $request->validate([
            'message'         => 'required|string|max:4000',
            'sender_staff_id' => 'required|integer',
        ]);

        $message = AtemMessage::create([
            'atem_id'         => $atem->id,
            'sender_staff_id' => $data['sender_staff_id'],
            'message'         => $data['message'],
        ]);

        $this->notifyForMessage($atem, $message);

        return response()->json(['success' => true, 'data' => $message], 201);
    }

    /**
     * PATCH /api/atem/{id}/messages/{messageId}
     * Only the original sender may edit, and only within EDIT_WINDOW_SECONDS
     * of posting. Ownership is checked against the row's own sender_staff_id
     * column (mirrors AtemNotificationController::markRead), not a cryptographic
     * proof, matching this app's trust model where odb's api.php is the boundary.
     */
    public function update(Request $request, int $id, int $messageId): JsonResponse
    {
        $message = AtemMessage::where('atem_id', $id)->findOrFail($messageId);

        $senderId = (int) $request->input('sender_staff_id', 0);
        if ($senderId === 0 || $senderId !== (int) $message->sender_staff_id) {
            return response()->json(['success' => false, 'message' => 'You can only edit your own messages.'], 403);
        }
        if (now()->diffInSeconds($message->created_at) > self::EDIT_WINDOW_SECONDS) {
            return response()->json(['success' => false, 'message' => 'The edit window for this message has expired.'], 403);
        }

        $data = $request->validate(['message' => 'required|string|max:4000']);
        $message->message = $data['message'];
        $message->save();

        return response()->json(['success' => true, 'data' => $message]);
    }

    /**
     * DELETE /api/atem/{id}/messages/{messageId}  (unsend)
     * Same ownership + time-window rule as update(). Soft-deletes the row.
     */
    public function destroy(Request $request, int $id, int $messageId): JsonResponse
    {
        $message = AtemMessage::where('atem_id', $id)->findOrFail($messageId);

        $senderId = (int) $request->input('sender_staff_id', 0);
        if ($senderId === 0 || $senderId !== (int) $message->sender_staff_id) {
            return response()->json(['success' => false, 'message' => 'You can only unsend your own messages.'], 403);
        }
        if (now()->diffInSeconds($message->created_at) > self::EDIT_WINDOW_SECONDS) {
            return response()->json(['success' => false, 'message' => 'The unsend window for this message has expired.'], 403);
        }

        $message->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Non-issuer posts -> notify the issuer only. Issuer replies -> notify every
     * current ARCI member (any role) excluding the issuer/sender, since a fresh
     * thread has no "prior posters" yet but does have known participants. The
     * sender is never notified of their own message.
     */
    private function notifyForMessage(Atem $atem, AtemMessage $message): void
    {
        $senderId = (int) $message->sender_staff_id;
        $issuerId = (int) $atem->issuer_staff_id;

        if ($senderId !== $issuerId) {
            $recipients = $issuerId > 0 ? [$issuerId] : [];
        } else {
            $recipients = $atem->arci()
                ->pluck('staff_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        foreach (array_unique($recipients) as $recipientId) {
            if ($recipientId === $senderId || $recipientId <= 0) {
                continue;
            }

            AtemNotification::create([
                'recipient_staff_id' => $recipientId,
                'type'               => 'chat_message',
                'atem_id'            => $atem->id,
                'atem_message_id'    => $message->id,
                'payload'            => [
                    'sender_staff_id' => $senderId,
                    'atem_title'      => $atem->title,
                    'snippet'         => Str::limit($message->message, 120),
                ],
            ]);
        }
    }
}
