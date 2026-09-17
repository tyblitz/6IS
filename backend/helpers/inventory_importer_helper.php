<?php
/**
 * Inventory Importer Helper
 * Functions for Property Number concatenation and Serial Number resolution
 */

/**
 * Concatenates property number components from the three columns under SEMI-EXPENDABLE PROPERTY NO.
 *
 * Rules:
 * 1. Columns are extracted left-to-right (col 1, col 2, col 3).
 * 2. Only non-blank values are concatenated.
 * 3. A hyphen (-) is added between each concatenated word.
 * 4. Leading zeroes (e.g. '0001', '0356') are preserved as strings.
 * 5. Outer whitespace and outer hyphens from components are trimmed before joining
 *    to avoid double-hyphens (e.g., '2021-05-03-HV' + '0001' + '-HSC' -> '2021-05-03-HV-0001-HSC').
 * 6. If all three columns are blank (or only hyphens/whitespace), returns 'N/A'.
 *
 * @param mixed $col1
 * @param mixed $col2
 * @param mixed $col3
 * @return string
 */
function extractPropertyNumber($col1, $col2, $col3): string {
    $rawCols = [$col1, $col2, $col3];
    $nonEmptyParts = [];

    foreach ($rawCols as $val) {
        if ($val === null) {
            continue;
        }
        // Force string to preserve leading zeroes
        $str = trim((string)$val);
        // Trim leading/trailing hyphens from individual part so joining with '-' produces clean single hyphens
        $clean = trim($str, "- \t\n\r\0\x0B");
        if ($clean !== '') {
            $nonEmptyParts[] = $clean;
        }
    }

    if (empty($nonEmptyParts)) {
        return 'N/A';
    }

    return implode('-', $nonEmptyParts);
}

/**
 * Resolves the serial number to store in database.
 *
 * Rules:
 * 1. If valid serial number is provided, store it trimmed.
 * 2. If no serial number can be extracted (blank or unavailable), return 'N/A'.
 * 3. Never return empty string or null.
 * 4. If updating an existing record that already has a valid serial number (not blank and not 'N/A'),
 *    preserve the existing serial number when the incoming value is empty or 'N/A'.
 *
 * @param mixed $newSerial
 * @param mixed $existingSerial
 * @return string
 */
function resolveSerialNumber($newSerial, $existingSerial = null): string {
    $cleanNew = ($newSerial !== null) ? trim((string)$newSerial) : '';

    // If an existing record has a valid serial number and the incoming value is empty or 'N/A', preserve existing
    if ($existingSerial !== null) {
        $cleanExisting = trim((string)$existingSerial);
        if ($cleanExisting !== '' && strtoupper($cleanExisting) !== 'N/A') {
            if ($cleanNew === '' || strtoupper($cleanNew) === 'N/A') {
                return $cleanExisting;
            }
        }
    }

    if ($cleanNew === '' || strtoupper($cleanNew) === 'N/A') {
        return 'N/A';
    }

    return $cleanNew;
}
