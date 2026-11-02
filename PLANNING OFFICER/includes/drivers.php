<?php
/**
 * Shared driver roster.
 *
 * One list, two consumers:
 *   - vehicle_requested.php assigns a driver to an approved trip and the value is
 *     stored in vehicle_requests.driver (reported in the summary/export).
 *   - travel_order_form.php renders the Driver dropdown on the printable form and
 *     re-selects the stored driver when the form is opened from a saved request.
 *
 * Previously the names were hardcoded inside travel_order_form.php only, so a
 * driver picked on the printed form was never persisted and could not be reported
 * in the summary. Keeping the roster here means both screens agree on the exact
 * same value strings - the select in the form can therefore always match the
 * name saved by the assignment dropdown, and a reprint shows the same driver.
 *
 * Add or retire a driver here and both screens pick the change up immediately.
 */
function getDrivers() {
    return [
        'Ryan B. Casama',
        'Ronnie A. Balanon',
    ];
}

/**
 * <option> markup for the driver roster, with $selected pre-selected.
 *
 * Escapes both values so a name can never break out of the attribute.
 *
 * @param string[] $drivers  Names from getDrivers()
 * @param string   $selected Currently stored driver ('' when unassigned)
 * @return string
 */
function driverOptionsHtml($drivers, $selected = '') {
    $html = '<option value="">Select Driver</option>';
    foreach ($drivers as $driver) {
        $html .= '<option value="' . htmlspecialchars($driver, ENT_QUOTES, 'UTF-8') . '"'
               . ($driver === $selected ? ' selected' : '') . '>'
               . htmlspecialchars($driver, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}
