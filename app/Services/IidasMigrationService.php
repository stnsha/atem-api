<?php

namespace App\Services;

use RuntimeException;
use Illuminate\Support\Facades\Log;

class IidasMigrationService extends OctopusApiService
{
    /**
     * Fetch a paginated list of IIDAS ATEMs from the ODB API.
     *
     * Returns ['data' => [...], 'total' => int, 'pages' => int].
     * Returns empty structure on failure so the caller can abort gracefully.
     */
    public function getAtems(int $page, int $perPage): array
    {
        try {
            $result = $this->callAPI('POST', 'iidas/atems.php', array(
                'username' => $this->username,
                'password' => $this->password,
                'page'     => $page,
                'limit'    => $perPage,
            ));

            return array(
                'data'  => isset($result['data'])  ? $result['data']  : array(),
                'total' => isset($result['total']) ? (int) $result['total'] : 0,
                'pages' => isset($result['pages']) ? (int) $result['pages'] : 1,
            );

        } catch (RuntimeException $e) {
            Log::warning('IidasMigrationService: getAtems failed', array(
                'error' => $e->getMessage(),
                'page'  => $page,
            ));

            return array('data' => array(), 'total' => 0, 'pages' => 0);
        }
    }

    /**
     * Fetch all relations (pics, subtasks, refs, attachments) for a batch of ATEM IDs.
     *
     * Returns ['pics' => [...], 'subtasks' => [...], 'refs' => [...], 'attachments' => [...]].
     * Returns empty arrays on failure so per-ATEM processing can continue.
     */
    public function getAtemRelations(array $ids): array
    {
        $empty = array('pics' => array(), 'subtasks' => array(), 'refs' => array(), 'attachments' => array());

        if (empty($ids)) {
            return $empty;
        }

        try {
            $result = $this->callAPI('POST', 'iidas/atem_relations.php', array(
                'username' => $this->username,
                'password' => $this->password,
                'ids'      => implode(',', array_map('intval', $ids)),
            ));

            return array(
                'pics'        => isset($result['pics'])        ? $result['pics']        : array(),
                'subtasks'    => isset($result['subtasks'])    ? $result['subtasks']    : array(),
                'refs'        => isset($result['refs'])        ? $result['refs']        : array(),
                'attachments' => isset($result['attachments']) ? $result['attachments'] : array(),
            );

        } catch (RuntimeException $e) {
            Log::warning('IidasMigrationService: getAtemRelations failed', array(
                'error' => $e->getMessage(),
                'ids'   => $ids,
            ));

            return $empty;
        }
    }
}
