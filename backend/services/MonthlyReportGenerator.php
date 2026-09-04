<?php
// backend/services/MonthlyReportGenerator.php
// Production DOCX Monthly Accomplishment Report Service for 6IS

class MonthlyReportGenerator {
    private ?PDO $pdo;
    private string $templatePath;

    public function __construct(?PDO $pdo = null, ?string $templatePath = null) {
        $this->pdo = $pdo;
        if ($templatePath && file_exists($templatePath)) {
            $this->templatePath = $templatePath;
        } else {
            $primaryPath = __DIR__ . '/../../templates/reports/monthly_accomplishment_report.docx';
            $fallbackPath = __DIR__ . '/../../templates/Monthly Accomplishment Report of OG6 April 2026 .docx';
            if (file_exists($primaryPath)) {
                $this->templatePath = $primaryPath;
            } elseif (file_exists($fallbackPath)) {
                $this->templatePath = $fallbackPath;
            } else {
                throw new Exception("Monthly accomplishment report template (.docx) not found.");
            }
        }
    }

    /**
     * Generate dynamic Monthly Accomplishment Report DOCX stream
     */
    public function generate(int $month, int $year): string {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Invalid month specified.");
        }
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException("Invalid year specified.");
        }

        $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
        $lastDay = date('t', mktime(0, 0, 0, $month, 1, $year));
        $periodStr = sprintf("01-%02d %s %d", $lastDay, $monthName, $year);

        // 1. Fetch Accomplishment Records
        $accomplishments = $this->fetchAccomplishments($month, $year);

        // 2. Fetch Outgoing Communications Statistics
        $outgoingStats = $this->fetchOutgoingCommunicationsStats($month, $year);

        // 3. Fetch Clearances Statistics
        $clearanceStats = $this->fetchClearanceStats($month, $year);

        // 4. Create temporary working file for ZipArchive
        $tempFile = tempnam(sys_get_temp_dir(), '6IS_MAR_') . '.docx';
        if (!copy($this->templatePath, $tempFile)) {
            throw new Exception("Failed to prepare report template for processing.");
        }

        $zip = new ZipArchive();
        if ($zip->open($tempFile) !== TRUE) {
            throw new Exception("Unable to open DOCX template archive.");
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if (!$documentXml) {
            $zip->close();
            @unlink($tempFile);
            throw new Exception("Corrupted template DOCX archive (missing word/document.xml).");
        }

        // 5. Manipulate XML using DOMDocument
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($documentXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // Replace text placeholders / month string references across runs
        $this->replacePhraseInParagraphs($xpath, '01-30 April 2026', $periodStr);
        $this->replacePhraseInParagraphs($xpath, 'April 2026', "{$monthName} {$year}");

        $tables = $xpath->query('//w:tbl');

        // Update Table #2 (Operations / Activities)
        if ($tables->length >= 2) {
            $this->updateActivitiesTable($dom, $xpath, $tables->item(1), $accomplishments);
        }

        // Update Table #3 (Outgoing Communications)
        if ($tables->length >= 3) {
            $this->updateOutgoingCommsTable($dom, $xpath, $tables->item(2), $outgoingStats);
        }

        // Update Table #4 (Clearances)
        if ($tables->length >= 4) {
            $this->updateClearanceTable($dom, $xpath, $tables->item(3), $clearanceStats);
        }

        // Save updated XML back into Zip archive
        $zip->addFromString('word/document.xml', $dom->saveXML());
        $zip->close();

        $outputBuffer = file_get_contents($tempFile);
        @unlink($tempFile);

        return $outputBuffer;
    }

    private function fetchAccomplishments(int $month, int $year): array {
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        a.id,
                        a.office_id,
                        a.date,
                        a.description,
                        a.remarks,
                        o.office_name,
                        o.office_code,
                        ac.category_name,
                        ac.category_code
                    FROM tbl_accomplishments a
                    LEFT JOIN tbl_offices o ON a.office_id = o.id
                    LEFT JOIN tbl_accomplishment_categories ac ON a.category_id = ac.id
                    WHERE MONTH(a.date) = :month 
                      AND YEAR(a.date) = :year 
                      AND a.deleted_at IS NULL
                    ORDER BY a.date ASC, a.id ASC
                ");
                $stmt->execute([':month' => $month, ':year' => $year]);
                return $stmt->fetchAll() ?: [];
            } catch (Exception $e) {
                return [];
            }
        }

        return [];
    }

    private function fetchOutgoingCommunicationsStats(int $month, int $year): array {
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        c.name AS category_name,
                        c.code AS category_code,
                        COUNT(com.id) AS total
                    FROM tbl_communications com
                    JOIN tbl_communication_categories c ON com.category_id = c.id
                    WHERE com.communication_type = 'Outgoing'
                      AND MONTH(com.communication_date) = :month
                      AND YEAR(com.communication_date) = :year
                      AND com.deleted_at IS NULL
                    GROUP BY c.id, c.name, c.code
                    ORDER BY total DESC
                ");
                $stmt->execute([':month' => $month, ':year' => $year]);
                return $stmt->fetchAll() ?: [];
            } catch (Exception $e) {
                return [];
            }
        }

        return [];
    }

    private function fetchClearanceStats(int $month, int $year): array {
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        p.name AS purpose_name,
                        COUNT(com.id) AS total
                    FROM tbl_communications com
                    JOIN tbl_communication_purposes p ON com.purpose_id = p.id
                    WHERE com.purpose_id = 1
                      AND MONTH(com.communication_date) = :month
                      AND YEAR(com.communication_date) = :year
                      AND com.deleted_at IS NULL
                    GROUP BY p.id, p.name
                ");
                $stmt->execute([':month' => $month, ':year' => $year]);
                $row = $stmt->fetch();
                return ['Access Pass' => $row ? (int)$row['total'] : 0];
            } catch (Exception $e) {
                return ['Access Pass' => 0];
            }
        }

        return ['Access Pass' => 0];
    }

    private function replacePhraseInParagraphs(DOMXPath $xpath, string $search, string $replace): void {
        $paragraphs = $xpath->query('//w:p');
        foreach ($paragraphs as $p) {
            $tNodes = $xpath->query('.//w:t', $p);
            if ($tNodes->length === 0) continue;

            $fullText = '';
            $nodeMap = [];
            for ($n = 0; $n < $tNodes->length; $n++) {
                $node = $tNodes->item($n);
                $val = $node->nodeValue;
                $len = strlen($val);
                for ($c = 0; $c < $len; $c++) {
                    $nodeMap[strlen($fullText) + $c] = ['node' => $node, 'offset' => $c, 'nodeIdx' => $n];
                }
                $fullText .= $val;
            }

            $pos = strpos($fullText, $search);
            if ($pos !== false) {
                $searchLen = strlen($search);
                $startNodeIdx = $nodeMap[$pos]['nodeIdx'];
                $endNodeIdx = $nodeMap[$pos + $searchLen - 1]['nodeIdx'];

                if ($startNodeIdx === $endNodeIdx) {
                    $node = $tNodes->item($startNodeIdx);
                    $node->nodeValue = str_replace($search, $replace, $node->nodeValue);
                } else {
                    $startOffset = $nodeMap[$pos]['offset'];
                    $startNode = $tNodes->item($startNodeIdx);
                    $startPrefix = substr($startNode->nodeValue, 0, $startOffset);
                    $startNode->nodeValue = $startPrefix . $replace;

                    $endOffset = $nodeMap[$pos + $searchLen - 1]['offset'];
                    $endNode = $tNodes->item($endNodeIdx);
                    $endSuffix = substr($endNode->nodeValue, $endOffset + 1);
                    $endNode->nodeValue = $endSuffix;

                    for ($m = $startNodeIdx + 1; $m < $endNodeIdx; $m++) {
                        $tNodes->item($m)->nodeValue = '';
                    }
                }
            }
        }
    }

    private function updateActivitiesTable(DOMDocument $dom, DOMXPath $xpath, DOMNode $tableNode, array $accomplishments): void {
        $rows = $xpath->query('.//w:tr', $tableNode);
        if ($rows->length < 2) return;

        $templateRow = $rows->item(1);

        // Remove existing sample rows except header
        for ($i = $rows->length - 1; $i >= 1; $i--) {
            $tableNode->removeChild($rows->item($i));
        }

        if (empty($accomplishments)) {
            $emptyRow = $templateRow->cloneNode(true);
            $cells = $xpath->query('.//w:tc', $emptyRow);
            if ($cells->length >= 3) {
                $this->setCellText($xpath, $cells->item(0), 'No accomplishment activities recorded for this period.');
                $this->setCellText($xpath, $cells->item(1), '0');
                $this->setCellText($xpath, $cells->item(2), 'None');
            }
            $tableNode->appendChild($emptyRow);
            return;
        }

        foreach ($accomplishments as $acc) {
            $newRow = $templateRow->cloneNode(true);
            $cells = $xpath->query('.//w:tc', $newRow);
            if ($cells->length >= 3) {
                $desc = $acc['description'];
                if (!empty($acc['category_code'])) {
                    $desc = "[{$acc['category_code']}] " . $desc;
                }
                $remarks = !empty($acc['remarks']) ? $acc['remarks'] : $acc['description'];
                $this->setCellText($xpath, $cells->item(0), $desc);
                $this->setCellText($xpath, $cells->item(1), '1');
                $this->setCellText($xpath, $cells->item(2), $remarks);
            }
            $tableNode->appendChild($newRow);
        }
    }

    private function updateOutgoingCommsTable(DOMDocument $dom, DOMXPath $xpath, DOMNode $tableNode, array $outgoingStats): void {
        $rows = $xpath->query('.//w:tr', $tableNode);
        if ($rows->length === 0) return;

        $templateRow = $rows->item(0);

        // Clear existing rows
        for ($i = $rows->length - 1; $i >= 0; $i--) {
            $tableNode->removeChild($rows->item($i));
        }

        $categories = [
            'Subject to Letter (STL)' => 0,
            'Disposition Form (DF)' => 0
        ];

        foreach ($outgoingStats as $stat) {
            $name = $stat['category_name'];
            if (!empty($stat['category_code'])) {
                $name .= " ({$stat['category_code']})";
            }
            $categories[$name] = (int)$stat['total'];
        }

        foreach ($categories as $catName => $count) {
            $newRow = $templateRow->cloneNode(true);
            $cells = $xpath->query('.//w:tc', $newRow);
            if ($cells->length >= 2) {
                $this->setCellText($xpath, $cells->item(0), $catName);
                $this->setCellText($xpath, $cells->item(1), (string)$count);
            }
            $tableNode->appendChild($newRow);
        }
    }

    private function updateClearanceTable(DOMDocument $dom, DOMXPath $xpath, DOMNode $tableNode, array $clearanceStats): void {
        $rows = $xpath->query('.//w:tr', $tableNode);
        if ($rows->length === 0) return;

        $row = $rows->item(0);
        $cells = $xpath->query('.//w:tc', $row);
        if ($cells->length >= 2) {
            $accessPassCount = isset($clearanceStats['Access Pass']) ? $clearanceStats['Access Pass'] : 0;
            $this->setCellText($xpath, $cells->item(0), 'Access Pass');
            $this->setCellText($xpath, $cells->item(1), (string)$accessPassCount);
        }
    }

    private function setCellText(DOMXPath $xpath, DOMNode $cellNode, string $text): void {
        $dom = $cellNode->ownerDocument;
        $pNodes = $xpath->query('.//w:p', $cellNode);
        if ($pNodes->length === 0) {
            $p = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p');
            $cellNode->appendChild($p);
        } else {
            $p = $pNodes->item(0);
            for ($i = $pNodes->length - 1; $i >= 1; $i--) {
                $cellNode->removeChild($pNodes->item($i));
            }
        }

        $rNodes = $xpath->query('.//w:r', $p);
        $rPr = null;
        if ($rNodes->length > 0) {
            $firstR = $rNodes->item(0);
            $rPrNodes = $xpath->query('.//w:rPr', $firstR);
            if ($rPrNodes->length > 0) {
                $rPr = $rPrNodes->item(0)->cloneNode(true);
            }
            for ($i = $rNodes->length - 1; $i >= 0; $i--) {
                $p->removeChild($rNodes->item($i));
            }
        }

        $newR = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
        if ($rPr) {
            $newR->appendChild($rPr);
        }
        $newT = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
        $newT->setAttribute('xml:space', 'preserve');
        $newT->nodeValue = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $newR->appendChild($newT);
        $p->appendChild($newR);
    }
}
