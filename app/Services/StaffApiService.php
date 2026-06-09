<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class StaffApiService extends OctopusApiService
{
    /**
     * Fetch grade and struct IDs for a list of staff IDs from the ODB API.
     *
     * Returns a collection keyed by staff_id:
     *   [ staff_id => ['grade' => int, 'struct' => int], ... ]
     *
     * @param array $staffIds
     * @return array
     */
    public function getStaffInfo(array $staffIds): array
    {
        if (empty($staffIds)) {
            return array();
        }

        try {
            $result = $this->callAPI('POST', 'staff/info.php', array(
                'username' => $this->username,
                'password' => $this->password,
                'ids'      => implode(',', array_map('intval', $staffIds)),
            ));

            $map = array();
            $items = isset($result['data']) ? $result['data'] : $result;
            foreach ($items as $row) {
                if (isset($row['id'])) {
                    $map[(int) $row['id']] = array(
                        'grade'  => isset($row['grade'])  ? (int) $row['grade']  : null,
                        'struct' => isset($row['struct']) ? (int) $row['struct'] : null,
                    );
                }
            }

            return $map;

        } catch (Exception $e) {
            Log::warning('StaffApiService: getStaffInfo failed', array(
                'error'     => $e->getMessage(),
                'staff_ids' => $staffIds,
            ));

            return array();
        }
    }

}
