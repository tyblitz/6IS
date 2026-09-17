<?php
// tests/unit/property_and_serial_number_test.php
// Verification Suite for Property Number Extraction & Serial Number Handling

require_once __DIR__ . '/../../backend/helpers/inventory_importer_helper.php';

$passCount = 0;
$failCount = 0;

function assertTest($condition, $testName, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$testName}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$testName} - {$details}\n";
    }
}

echo "===============================================================\n";
echo " 6IS PROPERTY NUMBER & SERIAL NUMBER EXTRACTION TEST SUITE\n";
echo "===============================================================\n\n";

// -------------------------------------------------------------
// SUITE 1: Property Number Concatenation Rules (Tests 1 to 8)
// -------------------------------------------------------------
echo "SUITE 1: Property Number Concatenation Rules\n";

// 1. All three components populated
$res1 = extractPropertyNumber('ABC', '2026', '001');
assertTest($res1 === 'ABC-2026-001', "Test 1: All three property-number components populated ('ABC' | '2026' | '001')", "Got: " . var_export($res1, true));

// 2. First component blank
$res2 = extractPropertyNumber('', '2026', '001');
assertTest($res2 === '2026-001', "Test 2: First component blank ('[blank]' | '2026' | '001')", "Got: " . var_export($res2, true));

// 3. Middle component blank
$res3 = extractPropertyNumber('ABC', '', '001');
assertTest($res3 === 'ABC-001', "Test 3: Middle component blank ('ABC' | '[blank]' | '001')", "Got: " . var_export($res3, true));

// 4. Last component blank
$res4 = extractPropertyNumber('ABC', '2026', '');
assertTest($res4 === 'ABC-2026', "Test 4: Last component blank ('ABC' | '2026' | '[blank]')", "Got: " . var_export($res4, true));

// 5. Two components blank
$res5a = extractPropertyNumber('', '', '001');
assertTest($res5a === '001', "Test 5A: Two components blank, right-only ('[blank]' | '[blank]' | '001')", "Got: " . var_export($res5a, true));

$res5b = extractPropertyNumber('CE-22-LV-0238', '', '');
assertTest($res5b === 'CE-22-LV-0238', "Test 5B: Two components blank, left-only ('CE-22-LV-0238' | '[blank]' | '[blank]')", "Got: " . var_export($res5b, true));

// 6. All three components blank -> 'N/A'
$res6a = extractPropertyNumber('', '', '');
assertTest($res6a === 'N/A', "Test 6A: All three components empty strings ('[blank]' | '[blank]' | '[blank]')", "Got: " . var_export($res6a, true));

$res6b = extractPropertyNumber(null, null, null);
assertTest($res6b === 'N/A', "Test 6B: All three components null", "Got: " . var_export($res6b, true));

$res6c = extractPropertyNumber('  ', ' - ', '');
assertTest($res6c === 'N/A', "Test 6C: All three components whitespace/hyphen only", "Got: " . var_export($res6c, true));

// 7. Property-number components containing leading zeroes
$res7 = extractPropertyNumber('', '0356', '-HSC');
assertTest($res7 === '0356-HSC', "Test 7: Leading zeroes strictly preserved ('[blank]' | '0356' | '-HSC')", "Got: " . var_export($res7, true));

// 8. Hyphen deduplication / normalization (no double hyphens '--')
$res8 = extractPropertyNumber('2021-05-03-HV', '0001', '-HSC');
assertTest($res8 === '2021-05-03-HV-0001-HSC', "Test 8: Existing hyphen normalization avoids '--' ('2021-05-03-HV' | '0001' | '-HSC')", "Got: " . var_export($res8, true));

// -------------------------------------------------------------
// SUITE 2: Serial Number Handling Rules (Tests 9 to 10)
// -------------------------------------------------------------
echo "\nSUITE 2: Serial Number Handling Rules\n";

// 9. Missing serial number stored as 'N/A'
$res9a = resolveSerialNumber(null);
assertTest($res9a === 'N/A', "Test 9A: Null serial number resolves to 'N/A'", "Got: " . var_export($res9a, true));

$res9b = resolveSerialNumber('');
assertTest($res9b === 'N/A', "Test 9B: Empty string serial number resolves to 'N/A'", "Got: " . var_export($res9b, true));

$res9c = resolveSerialNumber('   ');
assertTest($res9c === 'N/A', "Test 9C: Whitespace-only serial number resolves to 'N/A'", "Got: " . var_export($res9c, true));

$res9d = resolveSerialNumber('SN-VALID-12345');
assertTest($res9d === 'SN-VALID-12345', "Test 9D: Valid serial number preserved exactly", "Got: " . var_export($res9d, true));

// 10. Existing valid serial number not overwritten by 'N/A' on update
$res10a = resolveSerialNumber('', 'SN-ORIGINAL-999');
assertTest($res10a === 'SN-ORIGINAL-999', "Test 10A: Existing serial preserved when update value is empty string", "Got: " . var_export($res10a, true));

$res10b = resolveSerialNumber('N/A', 'SN-ORIGINAL-999');
assertTest($res10b === 'SN-ORIGINAL-999', "Test 10B: Existing serial preserved when update value is 'N/A'", "Got: " . var_export($res10b, true));

$res10c = resolveSerialNumber(null, 'SN-ORIGINAL-999');
assertTest($res10c === 'SN-ORIGINAL-999', "Test 10C: Existing serial preserved when update value is null", "Got: " . var_export($res10c, true));

$res10d = resolveSerialNumber('SN-NEW-REPLACEMENT', 'SN-ORIGINAL-999');
assertTest($res10d === 'SN-NEW-REPLACEMENT', "Test 10D: Existing serial updated when valid new serial is provided", "Got: " . var_export($res10d, true));

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n===============================================================\n";
echo " TEST SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "===============================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
