<?php
/**
 * Shared year-filter helpers.
 *
 * Years are derived from actual table data (plus the current year) rather than a
 * hardcoded `currentYear - N` range, so historical years can never fall off the
 * dropdown as time passes.
 */

/**
 * Years that actually contain data for a table/date-column, newest first.
 * The current year is always included so the default selection is always valid.
 *
 * @param mysqli $conn
 * @param string $table      e.g. 'vehicle_requests'
 * @param string $dateColumn e.g. 'travel_date'
 * @return int[]
 */
function getAvailableYears($conn, $table, $dateColumn)
{
    $allowed = [
        'vehicle_requests' => ['travel_date', 'request_date', 'created_at'],
        'requested_items'  => ['when_needed', 'request_date', 'status_updated_date', 'created_at'],
    ];

    if (!isset($allowed[$table]) || !in_array($dateColumn, $allowed[$table], true)) {
        return [date('Y')];
    }

    $years = [];
    $col = $conn->real_escape_string($dateColumn);
    $result = $conn->query("SELECT DISTINCT YEAR($col) AS y FROM $table
                            WHERE $col IS NOT NULL
                            ORDER BY y DESC");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['y'])) {
                $years[] = (int)$row['y'];
            }
        }
    }

    $current = (int)date('Y');
    if (!in_array($current, $years, true)) {
        $years[] = $current;
    }

    rsort($years);
    return $years;
}
