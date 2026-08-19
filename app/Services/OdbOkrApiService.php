<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Calls the plain-PHP endpoints in ODB's C:\laragon\www\api\ folder that
 * bridge okr_key_results.atem_id back to atem-api - ODB and atem-api run on
 * separate servers, so this is a real HTTP call, not a shared DB connection
 * (see okr_key_result_id rename migration + ReconcileOkrKeyResultLinks
 * command). Uses its own okr_odb_api credential, distinct from odb_api used
 * elsewhere in this app.
 */
class OdbOkrApiService extends OctopusApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->setCredentials(
            config('credentials.okr_odb_api.username') ?? '',
            config('credentials.okr_odb_api.password') ?? ''
        );
    }

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
}
