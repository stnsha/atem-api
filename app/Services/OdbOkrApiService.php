<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Calls the plain-PHP endpoints in ODB's api/ folder that bridge
 * okr_key_results.atem_id back to atem-api - ODB and atem-api run on
 * separate servers, so this is a real HTTP call, not a shared DB connection
 * (see okr_key_result_id rename migration + ReconcileOkrKeyResultLinks
 * command). Reuses the existing odb_api service account (config/credentials
 * odb_api, same one StaffApiService already uses against api_user) rather
 * than a separate credential - no new DB row needed.
 */
class OdbOkrApiService extends OctopusApiService
{
    /**
     * For each given ATEM id, find the okr_key_results row (if any) whose
     * atem_id points back to it. Returns a map:
     * atem_id => ['key_result_id' => int, 'card_id' => int, 'claim_count' => int].
     * claim_count > 1 means more than one Key Result row claims this same
     * atem_id (a distinct data problem - the command surfaces this as a
     * warning rather than silently picking one).
     * ATEM ids with no matching Key Result are simply absent from the map.
     *
     * @param array $atemIds
     * @return array<int, array{key_result_id: int, card_id: int, claim_count: int}>
     */
    public function lookupKeyResultsByAtemIds(array $atemIds): array
    {
        if (empty($atemIds)) {
            return array();
        }

        try {
            $result = $this->callAPI('POST', 'lookupOkrKeyResultByAtem.php', array(
                'username' => $this->username,
                'password' => $this->password,
                'atem_ids' => array_values(array_map('intval', $atemIds)),
            ));

            $map = array();
            $matches = isset($result['matches']) ? $result['matches'] : array();
            foreach ($matches as $row) {
                if (!isset($row['atem_id'])) {
                    continue;
                }
                $map[(int) $row['atem_id']] = array(
                    'key_result_id' => (int) $row['key_result_id'],
                    'card_id'       => (int) $row['card_id'],
                    'claim_count'   => isset($row['claim_count']) ? (int) $row['claim_count'] : 1,
                );
            }

            return $map;

        } catch (Exception $e) {
            Log::warning('OdbOkrApiService: lookupKeyResultsByAtemIds failed', array(
                'error'    => $e->getMessage(),
                'atem_ids' => $atemIds,
            ));

            return array();
        }
    }

    /**
     * Set (or clear, when $atemId is null) okr_key_results.atem_id for one
     * Key Result row. Not used by the reconciliation command's own null-out/
     * correct-in-place logic (that only touches atems.okr_key_result_id
     * locally) - available for call sites that need to push a link decision
     * onto the ODB side itself.
     *
     * @param int $keyResultId
     * @param int|null $atemId
     * @return bool
     */
    public function updateKeyResultAtem(int $keyResultId, ?int $atemId): bool
    {
        try {
            $result = $this->callAPI('POST', 'updateOkrKeyResultAtem.php', array(
                'username'      => $this->username,
                'password'      => $this->password,
                'key_result_id' => $keyResultId,
                'atem_id'       => $atemId,
            ));

            return isset($result['status']) && $result['status'] === 'success';

        } catch (Exception $e) {
            Log::warning('OdbOkrApiService: updateKeyResultAtem failed', array(
                'error'         => $e->getMessage(),
                'key_result_id' => $keyResultId,
                'atem_id'       => $atemId,
            ));

            return false;
        }
    }

    /**
     * Create a new okr_key_results row under $cardId (title copied from the
     * ATEM), pre-linked to $atemId via atem_id. Used by the reconciliation
     * command for orphaned atems.okr_key_result_id rows that have no
     * matching Key Result at all - rather than just nulling those out, a
     * Key Result is created to hold the link, using the ATEM's own title and
     * attributing created_by to the ATEM's issuer.
     * Returns the new key_result_id, or null on failure (e.g. the OKR card
     * no longer exists).
     *
     * Start/end date, when given, are copied from the ATEM's own timeline
     * onto the new Key Result (format Y-m-d) - omit either to leave it null.
     * $statusValue, when given, is matched by value against ODB's
     * okr_statuses (both modules use the same status name strings - Draft,
     * Active, Completed, Completed with Excellence, Extended, Failed,
     * Suspended, Completed with Extension - Force Terminated is OKR-only and
     * never comes from an ATEM); falls back to "Active" when omitted or
     * unmatched.
     *
     * @param int $cardId
     * @param string $title
     * @param int $atemId
     * @param int $createdBy
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $statusValue
     * @return int|null
     */
    public function createKeyResultForAtem(int $cardId, string $title, int $atemId, int $createdBy, ?string $startDate = null, ?string $endDate = null, ?string $statusValue = null): ?int
    {
        try {
            $result = $this->callAPI('POST', 'createOkrKeyResultForAtem.php', array(
                'username'     => $this->username,
                'password'     => $this->password,
                'card_id'      => $cardId,
                'title'        => $title,
                'atem_id'      => $atemId,
                'created_by'   => $createdBy,
                'start_date'   => $startDate,
                'end_date'     => $endDate,
                'status_value' => $statusValue,
            ));

            if (isset($result['status']) && $result['status'] === 'success' && isset($result['key_result_id'])) {
                return (int) $result['key_result_id'];
            }

            Log::warning('OdbOkrApiService: createKeyResultForAtem rejected', array(
                'response' => $result,
                'card_id'  => $cardId,
                'atem_id'  => $atemId,
            ));

            return null;

        } catch (Exception $e) {
            Log::warning('OdbOkrApiService: createKeyResultForAtem failed', array(
                'error'   => $e->getMessage(),
                'card_id' => $cardId,
                'atem_id' => $atemId,
            ));

            return null;
        }
    }
}
