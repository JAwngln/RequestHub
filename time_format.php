<?php
/**
 * Format a MySQL TIME value for display.
 *
 * "07:30:00" and "07:30" both become "7:30 AM"; "00:05" becomes "12:05 AM";
 * "13:45" becomes "1:45 PM". Empty or unrecognised values are returned as-is
 * (empty values become an empty string) so a bad row never blanks the page.
 */
function format_event_time($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
        return $value;
    }

    $hour = (int)$matches[1];
    $minute = (int)$matches[2];
    $second = isset($matches[3]) ? (int)$matches[3] : 0;

    if ($hour > 23 || $minute > 59 || $second > 59) {
        return $value;
    }

    $period = $hour >= 12 ? 'PM' : 'AM';
    $hour12 = $hour % 12;
    if ($hour12 === 0) {
        $hour12 = 12;
    }

    return $hour12 . ':' . str_pad((string)$minute, 2, '0', STR_PAD_LEFT) . ' ' . $period;
}
