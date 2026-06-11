<?php
require_once 'auth.php';
require_once 'payment_expiry_helpers.php';
require_role(3);

// Manual Bank In only: pending payments without receipt fail after 3 days.
mark_expired_manual_bank_payments($conn);

function clean_report_date($value, $fallback) {
    $value = trim((string)$value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    return $fallback;
}

function fetch_count_value($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) return 0;
    $row = mysqli_fetch_assoc($result);
    return $row ? ($row['total'] ?? 0) : 0;
}

function fetch_report_rows($conn, $sql, $keyName = 'label') {
    $rows = [];
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = [
                'label' => $row[$keyName] ?? '',
                'total' => $row['total'] ?? 0
            ];
        }
    }
    return $rows;
}

class SimplePdfReport {
    private $pages = [];
    private $content = '';
    private $pageWidth = 595.28;
    private $pageHeight = 841.89;
    private $margin = 45;
    private $y = 0;

    public function __construct() {
        $this->addPage();
    }

    private function pdfText($text) {
        $text = (string)$text;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
            if ($converted !== false) $text = $converted;
        }
        $text = str_replace(["\\", "(", ")", "\r", "\n", "\t"], ["\\\\", "\\(", "\\)", " ", " ", " "], $text);
        return $text;
    }

    private function cmd($command) {
        $this->content .= $command . "\n";
    }

    public function addPage() {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
        }
        $this->content = '';
        $this->y = $this->pageHeight - $this->margin;
    }

    private function ensureSpace($height) {
        if ($this->y - $height < $this->margin) {
            $this->addPage();
        }
    }

    public function text($x, $y, $text, $size = 11, $bold = false) {
        $font = $bold ? 'F2' : 'F1';
        $this->cmd('BT /' . $font . ' ' . $size . ' Tf ' . number_format($x, 2, '.', '') . ' ' . number_format($y, 2, '.', '') . ' Td (' . $this->pdfText($text) . ') Tj ET');
    }

    public function rect($x, $y, $w, $h, $fillGray = null, $strokeGray = null) {
        $parts = ['q'];
        if ($fillGray !== null) $parts[] = number_format($fillGray, 3, '.', '') . ' g';
        if ($strokeGray !== null) $parts[] = number_format($strokeGray, 3, '.', '') . ' G';
        $parts[] = number_format($x, 2, '.', '') . ' ' . number_format($y, 2, '.', '') . ' ' . number_format($w, 2, '.', '') . ' ' . number_format($h, 2, '.', '') . ' re';
        $parts[] = ($fillGray !== null && $strokeGray !== null) ? 'B' : (($fillGray !== null) ? 'f' : 'S');
        $parts[] = 'Q';
        $this->cmd(implode(' ', $parts));
    }

    public function line($x1, $y1, $x2, $y2, $gray = 0.75) {
        $this->cmd('q ' . number_format($gray, 3, '.', '') . ' G ' . number_format($x1, 2, '.', '') . ' ' . number_format($y1, 2, '.', '') . ' m ' . number_format($x2, 2, '.', '') . ' ' . number_format($y2, 2, '.', '') . ' l S Q');
    }

    public function title($title, $subtitle = '') {
        $this->rect(0, $this->pageHeight - 105, $this->pageWidth, 105, 0.94, null);
        $this->text($this->margin, $this->pageHeight - 55, $title, 22, true);
        if ($subtitle !== '') {
            $this->text($this->margin, $this->pageHeight - 80, $subtitle, 11, false);
        }
        $this->y = $this->pageHeight - 135;
    }

    public function section($title) {
        $this->ensureSpace(40);
        $this->text($this->margin, $this->y, $title, 15, true);
        $this->line($this->margin, $this->y - 8, $this->pageWidth - $this->margin, $this->y - 8, 0.82);
        $this->y -= 28;
    }

    public function summaryGrid($summary) {
        $this->ensureSpace(170);
        $cols = 2;
        $gap = 18;
        $cardW = (($this->pageWidth - ($this->margin * 2)) - $gap) / $cols;
        $cardH = 48;
        $xStart = $this->margin;
        $i = 0;
        foreach ($summary as $label => $value) {
            $col = $i % $cols;
            if ($i > 0 && $col === 0) {
                $this->y -= ($cardH + 12);
                $this->ensureSpace($cardH + 20);
            }
            $x = $xStart + ($col * ($cardW + $gap));
            $bottomY = $this->y - $cardH + 10;
            $this->rect($x, $bottomY, $cardW, $cardH, 0.98, 0.86);
            $this->text($x + 14, $bottomY + 27, $value, 17, true);
            $this->text($x + 14, $bottomY + 12, $label, 10, false);
            $i++;
        }
        $this->y -= ($cardH + 28);
    }

    public function table($title, $rows, $leftHeader, $rightHeader) {
        $rowH = 22;
        $needed = 52 + max(1, count($rows)) * $rowH;
        $this->ensureSpace(min($needed, 220));
        $this->section($title);
        $x = $this->margin;
        $w = $this->pageWidth - ($this->margin * 2);
        $leftW = $w * 0.72;
        $rightW = $w - $leftW;

        $this->rect($x, $this->y - 6, $w, $rowH, 0.94, 0.82);
        $this->text($x + 10, $this->y + 1, $leftHeader, 10, true);
        $this->text($x + $leftW + 10, $this->y + 1, $rightHeader, 10, true);
        $this->y -= $rowH;

        if (empty($rows)) {
            $this->rect($x, $this->y - 6, $w, $rowH, null, 0.88);
            $this->text($x + 10, $this->y + 1, 'No data available for the selected period.', 10, false);
            $this->y -= ($rowH + 12);
            return;
        }

        foreach ($rows as $row) {
            $this->ensureSpace($rowH + 16);
            $this->rect($x, $this->y - 6, $w, $rowH, null, 0.90);
            $this->text($x + 10, $this->y + 1, (string)$row['label'], 10, false);
            $this->text($x + $leftW + 10, $this->y + 1, number_format((float)$row['total'], 0), 10, false);
            $this->y -= $rowH;
        }
        $this->y -= 12;
    }

    public function footer($from, $to) {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
            $this->content = '';
        }
        $count = count($this->pages);
        for ($i = 0; $i < $count; $i++) {
            $footer = "\nq 0.55 G 45 35 m 550 35 l S Q\n";
            $footer .= 'BT /F1 8 Tf 45 22 Td (' . $this->pdfText('PawFect Home System Report | Period: ' . $from . ' to ' . $to) . ") Tj ET\n";
            $footer .= 'BT /F1 8 Tf 500 22 Td (' . $this->pdfText('Page ' . ($i + 1) . ' of ' . $count) . ") Tj ET\n";
            $this->pages[$i] .= $footer;
        }
    }

    public function output() {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $kids = [];
        $objNum = 5;
        foreach ($this->pages as $pageContent) {
            $pageObj = $objNum++;
            $contentObj = $objNum++;
            $kids[] = $pageObj . ' 0 R';
            $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $this->pageWidth . ' ' . $this->pageHeight . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObj . ' 0 R >>';
            $objects[$contentObj] = '<< /Length ' . strlen($pageContent) . " >>\nstream\n" . $pageContent . "endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($this->pages) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $num => $obj) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $obj . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i] ?? 0) . "\n";
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }
}

$from = clean_report_date($_GET['from'] ?? date('Y-m-01'), date('Y-m-01'));
$to = clean_report_date($_GET['to'] ?? date('Y-m-d'), date('Y-m-d'));
if (strtotime($from) > strtotime($to)) {
    $temp = $from;
    $from = $to;
    $to = $temp;
}

$fromSafe = mysqli_real_escape_string($conn, $from);
$toSafe = mysqli_real_escape_string($conn, $to);

$summaryRaw = [
    'Total Pets' => fetch_count_value($conn, "SELECT COUNT(*) total FROM pets"),
    'Available Pets' => fetch_count_value($conn, "SELECT COUNT(*) total FROM pets WHERE status='available'"),
    'Applications' => fetch_count_value($conn, "SELECT COUNT(*) total FROM adoption_applications WHERE DATE(created_at) BETWEEN '$fromSafe' AND '$toSafe'"),
    'Appointments' => fetch_count_value($conn, "SELECT COUNT(*) total FROM appointments WHERE appointment_date BETWEEN '$fromSafe' AND '$toSafe'"),
    'Product Payments' => fetch_count_value($conn, "SELECT COUNT(*) total FROM product_payments WHERE DATE(created_at) BETWEEN '$fromSafe' AND '$toSafe'"),
    'Product Collection (RM)' => fetch_count_value($conn, "SELECT COALESCE(SUM(amount),0) total FROM product_payments WHERE status='completed' AND DATE(created_at) BETWEEN '$fromSafe' AND '$toSafe'")
];

$summary = [];
foreach ($summaryRaw as $label => $value) {
    $summary[$label] = (strpos($label, 'RM') !== false) ? number_format((float)$value, 2) : number_format((float)$value, 0);
}

$appStatus = fetch_report_rows($conn, "SELECT status AS label, COUNT(*) total FROM adoption_applications GROUP BY status ORDER BY status", 'label');
$petTypes = fetch_report_rows($conn, "SELECT type AS label, COUNT(*) total FROM pets GROUP BY type ORDER BY type", 'label');
$productCats = fetch_report_rows($conn, "SELECT category AS label, COUNT(*) total FROM products GROUP BY category ORDER BY category", 'label');

$pdf = new SimplePdfReport();
$pdf->title('PawFect Home System Report', 'Report period: ' . $from . ' to ' . $to . ' | Generated: ' . date('Y-m-d H:i'));
$pdf->section('Summary');
$pdf->summaryGrid($summary);
$pdf->table('Application Status', $appStatus, 'Status', 'Total');
$pdf->table('Pets by Type', $petTypes, 'Pet Type', 'Total');
$pdf->table('Products by Category', $productCats, 'Category', 'Total');
$pdf->footer($from, $to);
$pdfContent = $pdf->output();

$filename = 'pawfect_report_' . str_replace('-', '', $from) . '_' . str_replace('-', '', $to) . '.pdf';
if (ob_get_length()) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;
exit;
