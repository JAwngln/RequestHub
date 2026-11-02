<?php
/**
 * Vehicle Fleet module schema.
 *
 * Keeps the fleet-specific tables and the vehicle <-> driver link in one place so
 * both the page (vehicle_fleet.php) and the JSON API (database_requests.php) see
 * the same structure, even on a fresh install where the page is opened first.
 *
 * Relationship:
 *   vehicle_fleet.driver_id -> drivers.id
 *     ON DELETE SET NULL  - deleting a driver simply unassigns their vehicle
 *     UNIQUE              - one driver can only be attached to one vehicle
 *
 * The unique index is deliberately on the vehicle side. It means a driver can
 * never be left "owning" two vehicles, and moving a driver between vehicles is a
 * single, atomic reassignment rather than two half-updated rows.
 */
if (!function_exists('ensureFleetSchema')) {
    function ensureFleetSchema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $conn->query("CREATE TABLE IF NOT EXISTS drivers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            license_number VARCHAR(50) NOT NULL,
            contact_number VARCHAR(50) NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_by VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_driver_license (license_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Older vehicle_fleet tables predate driver management.
        $col = $conn->query("SHOW COLUMNS FROM vehicle_fleet LIKE 'driver_id'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE vehicle_fleet ADD COLUMN driver_id INT NULL AFTER status");
        }

        // One driver, one vehicle. NULL is allowed for any number of vehicles.
        $idx = $conn->query("SHOW INDEX FROM vehicle_fleet WHERE Key_name = 'uniq_vehicle_driver'");
        if ($idx && $idx->num_rows === 0) {
            @$conn->query("ALTER TABLE vehicle_fleet ADD UNIQUE KEY uniq_vehicle_driver (driver_id)");
        }

        // Referential integrity: removing a driver can never leave a dangling id.
        $fk = $conn->query("SELECT CONSTRAINT_NAME
                            FROM information_schema.TABLE_CONSTRAINTS
                            WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = 'vehicle_fleet'
                              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                              AND CONSTRAINT_NAME = 'fk_vehicle_driver'");
        if ($fk && $fk->num_rows === 0) {
            @$conn->query("ALTER TABLE vehicle_fleet
                           ADD CONSTRAINT fk_vehicle_driver
                           FOREIGN KEY (driver_id) REFERENCES drivers(id)
                           ON DELETE SET NULL ON UPDATE CASCADE");
        }

        // The Planning Officer assigns the fleet vehicle during approval. Nullable
        // so old requests and not-yet-dispatched requests keep working.
        $vehicleRequests = $conn->query("SHOW TABLES LIKE 'vehicle_requests'");
        if ($vehicleRequests && $vehicleRequests->num_rows > 0) {
            $vehicleIdCol = $conn->query("SHOW COLUMNS FROM vehicle_requests LIKE 'vehicle_id'");
            if ($vehicleIdCol && $vehicleIdCol->num_rows === 0) {
                @$conn->query("ALTER TABLE vehicle_requests
                               ADD COLUMN vehicle_id INT NULL AFTER passengers");
            }
        }

        // Database-level guards. These mirror the PHP validation so a direct SQL
        // write cannot create an invalid plate/capacity or attach a request to a
        // vehicle that is in maintenance or retired.
        fleetEnsureTrigger($conn, 'trg_vehicle_fleet_bi', "CREATE TRIGGER trg_vehicle_fleet_bi
            BEFORE INSERT ON vehicle_fleet
            FOR EACH ROW
            BEGIN
                DECLARE plate_norm VARCHAR(40);
                SET plate_norm = REPLACE(REPLACE(REPLACE(UPPER(TRIM(NEW.plate_number)), ' ', ''), '-', ''), '.', '');
                IF CHAR_LENGTH(plate_norm) < 4 OR CHAR_LENGTH(plate_norm) > 12
                   OR plate_norm NOT REGEXP '^[A-Z0-9]+$'
                   OR plate_norm NOT REGEXP '[A-Z]'
                   OR plate_norm NOT REGEXP '[0-9]' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Plate number must be 4-12 letters/numbers with at least one letter and one digit.';
                END IF;
                IF NEW.capacity < 1 OR NEW.capacity > 80 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vehicle capacity must be between 1 and 80 passengers.';
                END IF;
                IF NEW.status = 'maintenance' AND NEW.last_maintenance IS NULL THEN
                    SET NEW.last_maintenance = CURDATE();
                END IF;
            END");

        fleetEnsureTrigger($conn, 'trg_vehicle_fleet_bu', "CREATE TRIGGER trg_vehicle_fleet_bu
            BEFORE UPDATE ON vehicle_fleet
            FOR EACH ROW
            BEGIN
                DECLARE plate_norm VARCHAR(40);
                SET plate_norm = REPLACE(REPLACE(REPLACE(UPPER(TRIM(NEW.plate_number)), ' ', ''), '-', ''), '.', '');
                IF CHAR_LENGTH(plate_norm) < 4 OR CHAR_LENGTH(plate_norm) > 12
                   OR plate_norm NOT REGEXP '^[A-Z0-9]+$'
                   OR plate_norm NOT REGEXP '[A-Z]'
                   OR plate_norm NOT REGEXP '[0-9]' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Plate number must be 4-12 letters/numbers with at least one letter and one digit.';
                END IF;
                IF NEW.capacity < 1 OR NEW.capacity > 80 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vehicle capacity must be between 1 and 80 passengers.';
                END IF;
                IF NEW.status = 'maintenance' AND (OLD.status <> 'maintenance' OR NEW.last_maintenance IS NULL) THEN
                    SET NEW.last_maintenance = CURDATE();
                END IF;
            END");

        if ($vehicleRequests && $vehicleRequests->num_rows > 0) {
            fleetEnsureTrigger($conn, 'trg_vehicle_requests_bi', "CREATE TRIGGER trg_vehicle_requests_bi
                BEFORE INSERT ON vehicle_requests
                FOR EACH ROW
                BEGIN
                    DECLARE vehicle_status VARCHAR(20) DEFAULT NULL;
                    IF NEW.vehicle_id IS NOT NULL THEN
                        SELECT status INTO vehicle_status FROM vehicle_fleet WHERE id = NEW.vehicle_id;
                        IF vehicle_status IS NULL THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The selected vehicle does not exist.';
                        ELSEIF vehicle_status <> 'available' THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The selected vehicle is not available because it is in maintenance or retired.';
                        END IF;
                    END IF;
                END");

            fleetEnsureTrigger($conn, 'trg_vehicle_requests_bu', "CREATE TRIGGER trg_vehicle_requests_bu
                BEFORE UPDATE ON vehicle_requests
                FOR EACH ROW
                BEGIN
                    DECLARE vehicle_status VARCHAR(20) DEFAULT NULL;
                    IF NEW.vehicle_id IS NOT NULL
                       AND (OLD.vehicle_id IS NULL OR NEW.vehicle_id <> OLD.vehicle_id) THEN
                        SELECT status INTO vehicle_status FROM vehicle_fleet WHERE id = NEW.vehicle_id;
                        IF vehicle_status IS NULL THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The selected vehicle does not exist.';
                        ELSEIF vehicle_status <> 'available' THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The selected vehicle is not available because it is in maintenance or retired.';
                        END IF;
                    END IF;
                END");
        }
    }
}

if (!function_exists('fleetEnsureTrigger')) {
    function fleetEnsureTrigger(mysqli $conn, string $name, string $sql): void
    {
        $stmt = $conn->prepare("SELECT 1
                                FROM information_schema.TRIGGERS
                                WHERE TRIGGER_SCHEMA = DATABASE()
                                  AND TRIGGER_NAME = ?
                                LIMIT 1");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        if (!$exists) {
            @$conn->query($sql);
        }
    }
}
