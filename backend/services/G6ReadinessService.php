<?php
// backend/services/G6ReadinessService.php
// Dedicated calculation and reporting data engine for 6IS G6 Equipment Readiness

class G6ReadinessService {

    /**
     * Current dataset reporting scope: ICT (1) and Communications (2).
     */
    public const SCOPE_EQUIPMENT_TYPE_IDS = [1, 2];

    /**
     * Map status text or status_id to operational, repair, or ber.
     *
     * @param int|null $statusId
     * @param string|null $statusText
     * @return string 'operational' | 'repair' | 'ber' | 'unknown'
     */
    public static function categorizeStatus(?int $statusId, ?string $statusText): string {
        $normText = trim(strtolower((string)$statusText));
        if ($normText !== '') {
            if (str_contains($normText, 'turn-in') || str_contains($normText, 'unserviceable') || str_contains($normText, 'ber')) {
                return 'ber';
            }
            if (str_contains($normText, 'repair')) {
                return 'repair';
            }
            if (str_contains($normText, 'serviceable') || str_contains($normText, 'operational')) {
                return 'operational';
            }
        }

        if ($statusId === 3) {
            return 'ber';
        }
        if ($statusId === 2) {
            return 'repair';
        }
        if ($statusId === 1) {
            return 'operational';
        }

        return 'unknown';
    }

    /**
     * Calculate REDCON category from rating decimal.
     *
     * Thresholds:
     *   >= 0.85 -> R1
     *   >= 0.75 -> R2
     *   >  0.50 -> R3
     *   <= 0.50 -> R4
     *   null    -> R4
     *
     * @param float|null $rating
     * @return string
     */
    public static function calculateRedcon(?float $rating): string {
        if ($rating === null) {
            return 'R4';
        }
        if ($rating >= 0.85) {
            return 'R1';
        }
        if ($rating >= 0.75) {
            return 'R2';
        }
        if ($rating > 0.50) {
            return 'R3';
        }
        return 'R4';
    }

    /**
     * Calculate unweighted average of an array of ratings, excluding nulls.
     *
     * @param array<float|null> $ratings
     * @return float|null
     */
    public static function calculateUnweightedAverage(array $ratings): ?float {
        $valid = [];
        foreach ($ratings as $r) {
            if ($r !== null && is_numeric($r)) {
                $valid[] = (float)$r;
            }
        }
        if (empty($valid)) {
            return null;
        }
        return round(array_sum($valid) / count($valid), 4);
    }

    /**
     * Calculate deterministic single-line metrics.
     *
     * @param int $subtypeId
     * @param string $nomenclature
     * @param int $typeId
     * @param string $typeName
     * @param int $required
     * @param int $operational
     * @param int $repair
     * @param int $ber
     * @return array
     */
    public static function calculateLine(
        int $subtypeId,
        string $nomenclature,
        int $typeId,
        string $typeName,
        int $required,
        int $operational,
        int $repair,
        int $ber,
        ?string $category = null,
        ?string $subCategory = null,
        int $sortOrder = 0,
        int $jrrsId = 0
    ): array {
        $onHand = $operational + $repair + $ber;
        $deficit = max(0, $required - $onHand);

        $eqRating = ($required > 0) ? min(1.0, $onHand / $required) : null;
        $maintRating = ($onHand > 0) ? min(1.0, $operational / $onHand) : null;

        return [
            'id' => $jrrsId ?: $subtypeId,
            'jrrs_id' => $jrrsId ?: $subtypeId,
            'equipment_subtype_id' => $subtypeId,
            'nomenclature' => $nomenclature,
            'equipment_subtype' => $nomenclature,
            'equipment_type_id' => $typeId,
            'equipment_type_name' => $typeName,
            'equipment_type' => $category ?: $typeName,
            'category' => $category ?: $typeName,
            'sub_category' => $subCategory,
            'sort_order' => $sortOrder,
            'required' => $required,
            'target_quantity' => $required,
            'operational' => $operational,
            'repair' => $repair,
            'ber' => $ber,
            'on_hand' => $onHand,
            'current_quantity' => $onHand,
            'deficit' => $deficit,
            'shortage' => $deficit,
            'equipment_rating' => $eqRating !== null ? round($eqRating, 4) : null,
            'maintenance_rating' => $maintRating !== null ? round($maintRating, 4) : null,
            'readiness_pct' => $eqRating !== null ? round($eqRating * 100, 1) : 0.0,
            'equipment_redcon' => self::calculateRedcon($eqRating),
            'maintenance_redcon' => self::calculateRedcon($maintRating)
        ];
    }

    /**
     * Map historical legacy snapshot text to equipment_subtype_id and equipment_type_id.
     * Used exclusively for preserved pre-migration snapshots (e.g. 2026-06, 2026-07).
     *
     * @param string|null $equipmentType
     * @return array{0: int|null, 1: int|null} [subtype_id, type_id]
     */
    public static function mapLegacyHistoricalSubtype(?string $equipmentType): array {
        $t = trim(strtolower((string)$equipmentType));
        if (str_contains($t, 'desktop')) {
            return [1, 1]; // Desktop, ICT
        }
        if (str_contains($t, 'printer')) {
            return [2, 1]; // Printer, ICT
        }
        if (str_contains($t, 'laptop')) {
            return [6, 1]; // Laptop, ICT
        }
        if (str_contains($t, 'switch')) {
            return [7, 1]; // Network Switch, ICT
        }
        if (str_contains($t, 'public address') || str_contains($t, 'pa')) {
            return [11, 2]; // Public Address System, Communications
        }
        return [null, null];
    }

    /**
     * Calculate G6 readiness data payload for the requested period.
     *
     * @param PDO $pdo
     * @param string|null $period 'YYYY-MM' or null for current
     * @return array
     */
    public static function calculate(PDO $pdo, ?string $period = null): array {
        $currentYearMonth = date('Y-m');

        if ($period !== null && $period !== '') {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
                throw new InvalidArgumentException("Invalid reporting period format: '{$period}'. Expected YYYY-MM.");
            }
            $selectedPeriod = $period;
        } else {
            $selectedPeriod = $currentYearMonth;
        }

        $isCurrent = ($selectedPeriod === $currentYearMonth);

        // Fetch JRRS target definitions (Section III. C4ISTAR)
        $jrrsStmt = $pdo->prepare("
            SELECT j.id, j.category, j.sub_category, j.sort_order, j.equipment_type, j.target_quantity
            FROM tbl_inventory_jrrs j
            WHERE j.deleted_at IS NULL
            ORDER BY j.sort_order ASC, j.id ASC
        ");
        $jrrsStmt->execute();
        $jrrsTargets = $jrrsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch many-to-one subtype mappings
        $mapStmt = $pdo->prepare("SELECT jrrs_id, equipment_subtype_id FROM tbl_inventory_jrrs_subtypes");
        $mapStmt->execute();
        $mappings = $mapStmt->fetchAll(PDO::FETCH_ASSOC);

        $subtypeToJrrsMap = [];
        foreach ($mappings as $m) {
            $stId = (int)$m['equipment_subtype_id'];
            $jId = (int)$m['jrrs_id'];
            $subtypeToJrrsMap[$stId][] = $jId;
        }

        // Track equipment counts indexed by jrrs_id
        $countsByJrrs = [];
        foreach ($jrrsTargets as $jrrs) {
            $jId = (int)$jrrs['id'];
            $countsByJrrs[$jId] = [
                'operational' => 0,
                'repair' => 0,
                'ber' => 0
            ];
        }

        if ($isCurrent) {
            $hasSnapshot = true;

            // Fetch live in-scope equipment using relational subtype ID
            $eqStmt = $pdo->prepare("
                SELECT id, equipment_subtype_id, equipment_type_id, status_id, status
                FROM tbl_inventory_equipment
                WHERE deleted_at IS NULL
            ");
            $eqStmt->execute();
            $equipRecords = $eqStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($equipRecords as $row) {
                $stId = (int)($row['equipment_subtype_id'] ?? 0);
                if (isset($subtypeToJrrsMap[$stId])) {
                    $cat = self::categorizeStatus(
                        $row['status_id'] !== null ? (int)$row['status_id'] : null,
                        $row['status'] ?? null
                    );
                    if ($cat !== 'unknown') {
                        foreach ($subtypeToJrrsMap[$stId] as $jId) {
                            if (isset($countsByJrrs[$jId])) {
                                $countsByJrrs[$jId][$cat]++;
                            }
                        }
                    }
                }
            }
        } else {
            // Historical mode: Check if snapshot exists for requested period
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_history WHERE `year_month` = :period");
            $checkStmt->execute([':period' => $selectedPeriod]);
            $snapshotCount = (int)$checkStmt->fetchColumn();

            if ($snapshotCount === 0) {
                return [
                    'period' => $selectedPeriod,
                    'period_label' => self::formatPeriodLabel($selectedPeriod),
                    'mode' => 'historical',
                    'has_snapshot' => false,
                    'message' => "No snapshot data recorded for period {$selectedPeriod}."
                ];
            }

            $hasSnapshot = true;

            // Retrieve snapshot records without joining live tbl_inventory_equipment
            $histStmt = $pdo->prepare("
                SELECT id, equipment_id, equipment_type_id, equipment_subtype_id, status_id,
                       equipment_type, status
                FROM tbl_inventory_history
                WHERE `year_month` = :period
            ");
            $histStmt->execute([':period' => $selectedPeriod]);
            $histRecords = $histStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($histRecords as $row) {
                $stId = $row['equipment_subtype_id'] !== null ? (int)$row['equipment_subtype_id'] : null;

                // For legacy snapshots where IDs were null, derive from preserved historical text
                if ($stId === null) {
                    [$mappedStId, $mappedTId] = self::mapLegacyHistoricalSubtype($row['equipment_type'] ?? null);
                    $stId = $mappedStId;
                }

                if ($stId !== null && isset($subtypeToJrrsMap[$stId])) {
                    $cat = self::categorizeStatus(
                        $row['status_id'] !== null ? (int)$row['status_id'] : null,
                        $row['status'] ?? null
                    );
                    if ($cat !== 'unknown') {
                        foreach ($subtypeToJrrsMap[$stId] as $jId) {
                            if (isset($countsByJrrs[$jId])) {
                                $countsByJrrs[$jId][$cat]++;
                            }
                        }
                    }
                }
            }
        }

        // Standard C4ISTAR military category prefixes & order
        $categoryLabels = [
            'COMMUNICATIONS' => 'A. COMMUNICATIONS',
            'CYBER SECURITY' => 'B. CYBER SECURITY',
            'CENSOR INTEGRATION SYSTEM' => 'C. CENSOR INTEGRATION SYSTEM',
            'INFORMATION MANAGEMENT SYSTEM' => 'D. INFORMATION MANAGEMENT SYSTEM',
            'OTHER C2 SYSTEM' => 'E. OTHER C2 SYSTEM'
        ];
        $categoryIds = [
            'COMMUNICATIONS' => 1,
            'CYBER SECURITY' => 2,
            'CENSOR INTEGRATION SYSTEM' => 3,
            'INFORMATION MANAGEMENT SYSTEM' => 4,
            'OTHER C2 SYSTEM' => 5
        ];

        // Build line items
        $lines = [];
        $groupsMap = [];

        foreach ($jrrsTargets as $jrrs) {
            $jId = (int)$jrrs['id'];
            $catKey = $jrrs['category'] ?: 'OTHER C2 SYSTEM';
            $catName = $categoryLabels[$catKey] ?? $catKey;
            $catId = $categoryIds[$catKey] ?? 1;
            $nomenclature = $jrrs['equipment_type'];
            $subCategory = $jrrs['sub_category'];
            $sortOrder = (int)$jrrs['sort_order'];
            $target = (int)$jrrs['target_quantity'];

            $cnt = $countsByJrrs[$jId] ?? ['operational' => 0, 'repair' => 0, 'ber' => 0];

            $line = self::calculateLine(
                $jId,
                $nomenclature,
                $catId,
                $catName,
                $target,
                $cnt['operational'],
                $cnt['repair'],
                $cnt['ber'],
                $catKey,
                $subCategory,
                $sortOrder,
                $jId
            );

            $lines[] = $line;

            if (!isset($groupsMap[$catKey])) {
                $groupsMap[$catKey] = [
                    'group_id' => $catId,
                    'category' => $catKey,
                    'group_name' => $catName,
                    'lines' => [],
                    'totals' => [
                        'required' => 0,
                        'operational' => 0,
                        'repair' => 0,
                        'ber' => 0,
                        'on_hand' => 0,
                        'deficit' => 0
                    ]
                ];
            }

            $groupsMap[$catKey]['lines'][] = $line;
            $groupsMap[$catKey]['totals']['required'] += $line['required'];
            $groupsMap[$catKey]['totals']['operational'] += $line['operational'];
            $groupsMap[$catKey]['totals']['repair'] += $line['repair'];
            $groupsMap[$catKey]['totals']['ber'] += $line['ber'];
            $groupsMap[$catKey]['totals']['on_hand'] += $line['on_hand'];
            $groupsMap[$catKey]['totals']['deficit'] += $line['deficit'];
        }

        // Calculate unweighted group ratings
        $groups = [];
        $groupEqRatings = [];
        $groupMaintRatings = [];

        $grandTotals = [
            'required' => 0,
            'operational' => 0,
            'repair' => 0,
            'ber' => 0,
            'on_hand' => 0,
            'deficit' => 0
        ];

        foreach ($groupsMap as $gKey => $group) {
            $lineEqRatings = array_column($group['lines'], 'equipment_rating');
            $lineMaintRatings = array_column($group['lines'], 'maintenance_rating');

            $gEqRating = self::calculateUnweightedAverage($lineEqRatings);
            $gMaintRating = self::calculateUnweightedAverage($lineMaintRatings);

            $group['equipment_rating'] = $gEqRating;
            $group['maintenance_rating'] = $gMaintRating;
            $group['equipment_redcon'] = self::calculateRedcon($gEqRating);
            $group['maintenance_redcon'] = self::calculateRedcon($gMaintRating);

            $groups[] = $group;

            if ($gEqRating !== null) {
                $groupEqRatings[] = $gEqRating;
            }
            if ($gMaintRating !== null) {
                $groupMaintRatings[] = $gMaintRating;
            }

            $grandTotals['required'] += $group['totals']['required'];
            $grandTotals['operational'] += $group['totals']['operational'];
            $grandTotals['repair'] += $group['totals']['repair'];
            $grandTotals['ber'] += $group['totals']['ber'];
            $grandTotals['on_hand'] += $group['totals']['on_hand'];
            $grandTotals['deficit'] += $group['totals']['deficit'];
        }

        // Hierarchical overall rating = unweighted average of group ratings
        $overallEqRating = self::calculateUnweightedAverage($groupEqRatings);
        $overallMaintRating = self::calculateUnweightedAverage($groupMaintRatings);

        return [
            'period' => $selectedPeriod,
            'period_label' => self::formatPeriodLabel($selectedPeriod),
            'mode' => $isCurrent ? 'current' : 'historical',
            'has_snapshot' => $hasSnapshot,
            'scope' => [
                'equipment_type_ids' => self::SCOPE_EQUIPMENT_TYPE_IDS,
                'description' => 'Current dataset scope: ICT (1) and Communications (2)'
            ],
            'lines' => $lines,
            'groups' => $groups,
            'summary' => [
                'totals' => $grandTotals,
                'equipment_rating' => $overallEqRating,
                'maintenance_rating' => $overallMaintRating,
                'equipment_redcon' => self::calculateRedcon($overallEqRating),
                'maintenance_redcon' => self::calculateRedcon($overallMaintRating)
            ]
        ];
    }

    /**
     * Format YYYY-MM label.
     */
    private static function formatPeriodLabel(string $ym): string {
        $dt = DateTime::createFromFormat('Y-m', $ym);
        return $dt ? $dt->format('F Y') : $ym;
    }
}
