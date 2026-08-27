<?php
// database/seed_calendar_activities.php
// Script to populate June, July, August, September 2026 with operational activities
// Weekdays only (Mon-Fri), max 2 activities per day.

$host = 'localhost';
$username = 'root';
$password = '';
$dbName = 'db_ict_system';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbName};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "Connected to database '{$dbName}'.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Target months: 2026-06, 2026-07, 2026-08, 2026-09
$months = [
    ['year' => 2026, 'month' => 6, 'days' => 30],
    ['year' => 2026, 'month' => 7, 'days' => 31],
    ['year' => 2026, 'month' => 8, 'days' => 31],
    ['year' => 2026, 'month' => 9, 'days' => 30]
];

// Reference Data pools
$accomplishmentPool = [
    ['cat' => 1, 'desc' => 'Setup Public Address System (PAS) for grand assembly and briefing.', 'rem' => 'PAS tested clear.'],
    ['cat' => 2, 'desc' => 'Conducted preventative maintenance on network switch hardware & server racks.', 'rem' => 'Switch fans cleaned.'],
    ['cat' => 3, 'desc' => 'Supervised TELCO technicians during fiber line restoration inside camp.', 'rem' => 'Link online at 1Gbps.'],
    ['cat' => 4, 'desc' => 'Provided LED board technical support for briefing presentation.', 'rem' => 'Display active throughout.'],
    ['cat' => 2, 'desc' => 'Repaired corrupt OS drive and restored administrative workstation.', 'rem' => 'System re-imaged.'],
    ['cat' => 1, 'desc' => 'Deployed wireless PAS audio set for battalion formation.', 'rem' => 'Audio balanced.'],
    ['cat' => 3, 'desc' => 'Assisted Globe TELCO team in relocating telephone cabling for G6 expansion.', 'rem' => 'Lines reconnected.'],
    ['cat' => 2, 'desc' => 'Replaced failing UPS batteries and serviced power supply units.', 'rem' => 'Backup runtime 45 mins.'],
    ['cat' => 4, 'desc' => 'Configured LED board display input controller software.', 'rem' => 'Calibrated resolution.'],
    ['cat' => 2, 'desc' => 'Diagnosed motherboard failure and replaced damaged RAM modules.', 'rem' => 'Hardware benchmarked.']
];

$incomingCommPool = [
    ['cat' => 1, 'purp' => 2, 'sub' => 'Request for IT Infrastructure Audit & Security Patching', 'status' => 'In Progress'],
    ['cat' => 3, 'purp' => 1, 'sub' => 'Application for Server Room Access Pass for Q3', 'status' => 'Completed'],
    ['cat' => 2, 'purp' => 3, 'sub' => 'Endorsement for Additional Network Switches Procurement', 'status' => 'Pending'],
    ['cat' => 4, 'purp' => 4, 'sub' => 'Inquiry on Fiber Optic Backbone Link Cable Upgrade', 'status' => 'In Progress'],
    ['cat' => 1, 'purp' => 2, 'sub' => 'Request for Database Access Credentials Provisioning', 'status' => 'In Progress'],
    ['cat' => 5, 'purp' => 4, 'sub' => 'Request for Cyber Hygiene Workshop Schedule Confirmation', 'status' => 'Completed'],
    ['cat' => 3, 'purp' => 1, 'sub' => 'Temporary Datacenter Entry Request for External Technicians', 'status' => 'Pending'],
    ['cat' => 2, 'purp' => 3, 'sub' => 'Funding Allocation Clarification for Annual Cloud Server License', 'status' => 'In Progress'],
    ['cat' => 4, 'purp' => 4, 'sub' => 'Transmittal of Annual Hardware Equipment Inventory Report', 'status' => 'Completed']
];

$outgoingCommPool = [
    ['cat' => 4, 'purp' => 3, 'sub' => 'Memo on Quarterly Hardware Procurement & Maintenance Budget', 'status' => 'Pending'],
    ['cat' => 5, 'purp' => 4, 'sub' => 'Guidelines on Information Systems Security & Password Management', 'status' => 'Released'],
    ['cat' => 1, 'purp' => 2, 'sub' => 'Dispatch of Final Migration Plan for Enterprise ERP System', 'status' => 'In Progress'],
    ['cat' => 3, 'purp' => 1, 'sub' => 'Issuance of Revoked Access Badges Advisory to All Units', 'status' => 'Released'],
    ['cat' => 2, 'purp' => 3, 'sub' => 'Transmittal of ICT Equipment Depreciation Assessment', 'status' => 'Pending'],
    ['cat' => 4, 'purp' => 4, 'sub' => 'Advisory on Scheduled Server Room Electrical Maintenance Power Down', 'status' => 'Released'],
    ['cat' => 5, 'purp' => 4, 'sub' => 'Standard Protocol for Data Backup and Disaster Recovery Tests', 'status' => 'Completed']
];

$calendarEventPool = [
    ['title' => 'Weekly Staff Coordination Meeting', 'desc' => 'Regular weekly coordination meeting with all G6 personnel.', 'type' => 'meeting', 'prio' => 'normal', 'loc' => 'G6 Conference Room', 'time' => '09:00:00'],
    ['title' => 'Quarterly IT Infrastructure Audit Deadline', 'desc' => 'Deadline for submitting IT infrastructure audit reports.', 'type' => 'deadline', 'prio' => 'high', 'loc' => 'Datacenter', 'time' => '17:00:00'],
    ['title' => 'Scheduled Server Maintenance Window', 'desc' => 'Scheduled server patching and firmware updates.', 'type' => 'reminder', 'prio' => 'high', 'loc' => 'Server Room A', 'time' => '20:00:00'],
    ['title' => 'Monthly Equipment Inventory Physical Count', 'desc' => 'Conduct physical count of on-hand ICT equipment.', 'type' => 'other', 'prio' => 'normal', 'loc' => 'Supply Warehouse', 'time' => '10:00:00'],
    ['title' => 'Network Security Review Briefing', 'desc' => 'Review firewall policy rules and ACL configurations.', 'type' => 'meeting', 'prio' => 'high', 'loc' => 'Briefing Hall', 'time' => '14:00:00'],
    ['title' => 'Budget Proposal Submission Deadline', 'desc' => 'Submit next fiscal year ICT budget proposal to Finance.', 'type' => 'deadline', 'prio' => 'high', 'loc' => 'Finance Office', 'time' => '12:00:00'],
    ['title' => 'Cybersecurity Hygiene Refresher Training', 'desc' => 'Quarterly cybersecurity awareness session.', 'type' => 'meeting', 'prio' => 'normal', 'loc' => 'Auditorium', 'time' => '13:30:00']
];

// Clean existing data for June, July, August, September 2026
$pdo->exec("DELETE FROM tbl_accomplishments WHERE date BETWEEN '2026-06-01' AND '2026-09-30'");
$pdo->exec("DELETE FROM tbl_communications WHERE communication_date BETWEEN '2026-06-01' AND '2026-09-30'");
$pdo->exec("DELETE FROM tbl_calendar_events WHERE event_date BETWEEN '2026-06-01' AND '2026-09-30'");

$accCount = 0;
$incCount = 0;
$outCount = 0;
$evtCount = 0;

$offices = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16];

foreach ($months as $m) {
    $y = $m['year'];
    $monthNum = $m['month'];
    $numDays = $m['days'];

    for ($d = 1; $d <= $numDays; $d++) {
        $dateStr = sprintf('%04d-%02d-%02d', $y, $monthNum, $d);
        $dayOfWeek = date('N', strtotime($dateStr)); // 1=Mon, 7=Sun

        // Skip weekends (Saturday=6, Sunday=7)
        if ($dayOfWeek >= 6) {
            continue;
        }

        // Determine activity count for this day (1 or 2 activities, with occasional 0)
        // Pseudo-random deterministic distribution based on date
        $seed = ($y * 10000) + ($monthNum * 100) + $d;
        mt_srand($seed);

        $activityCount = mt_rand(1, 2); // 1 or 2 activities per weekday
        if ($d % 7 === 0) $activityCount = 0; // Occasional light weekday

        for ($actIdx = 0; $actIdx < $activityCount; $actIdx++) {
            // Source selector: 0=Accomplishment, 1=Incoming Comm, 2=Outgoing Comm, 3=Calendar Event
            $sourceType = mt_rand(0, 3);
            $officeId = $offices[mt_rand(0, count($offices) - 1)];

            if ($sourceType === 0) {
                // Accomplishment
                $acc = $accomplishmentPool[mt_rand(0, count($accomplishmentPool) - 1)];
                $stmt = $pdo->prepare("
                    INSERT INTO tbl_accomplishments (office_id, category_id, date, description, remarks, created_at, updated_at, created_by)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1)
                ");
                $stmt->execute([$officeId, $acc['cat'], $dateStr, $acc['desc'], $acc['rem']]);
                $accCount++;
            } elseif ($sourceType === 1) {
                // Incoming Communication
                $inc = $incomingCommPool[mt_rand(0, count($incomingCommPool) - 1)];
                $stmt = $pdo->prepare("
                    INSERT INTO tbl_communications (communication_type, office_id, category_id, purpose_id, subject, communication_date, status, created_at, updated_at, created_by)
                    VALUES ('Incoming', ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)
                ");
                $stmt->execute([$officeId, $inc['cat'], $inc['purp'], $inc['sub'], $dateStr, $inc['status']]);
                $incCount++;
            } elseif ($sourceType === 2) {
                // Outgoing Communication
                $out = $outgoingCommPool[mt_rand(0, count($outgoingCommPool) - 1)];
                $stmt = $pdo->prepare("
                    INSERT INTO tbl_communications (communication_type, office_id, category_id, purpose_id, subject, communication_date, status, created_at, updated_at, created_by)
                    VALUES ('Outgoing', ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)
                ");
                $stmt->execute([$officeId, $out['cat'], $out['purp'], $out['sub'], $dateStr, $out['status']]);
                $outCount++;
            } else {
                // Standalone Calendar Event
                $evt = $calendarEventPool[mt_rand(0, count($calendarEventPool) - 1)];
                $startDt = $dateStr . ' ' . $evt['time'];
                $endDt = date('Y-m-d H:i:s', strtotime($startDt . ' +1 hour'));
                $stmt = $pdo->prepare("
                    INSERT INTO tbl_calendar_events (title, description, event_date, event_time, start_datetime, end_datetime, all_day, location, event_type, priority, status, created_at, updated_at, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'Scheduled', NOW(), NOW(), 1)
                ");
                $stmt->execute([$evt['title'], $evt['desc'], $dateStr, $evt['time'], $startDt, $endDt, $evt['loc'], $evt['type'], $evt['prio']]);
                $evtCount++;
            }
        }
    }
}

echo "SUCCESSFULLY SEEDED CALENDAR ACTIVITIES (June – September 2026):\n";
echo " - Accomplishments: {$accCount}\n";
echo " - Incoming Communications: {$incCount}\n";
echo " - Outgoing Communications: {$outCount}\n";
echo " - Standalone Calendar Events: {$evtCount}\n";
echo " Total activities created: " . ($accCount + $incCount + $outCount + $evtCount) . "\n";
