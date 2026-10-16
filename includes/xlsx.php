<?php
/**
 * Minimal .xlsx (Excel) writer - no libraries or Composer needed.
 * Requires PHP's "zip" extension (enabled by default in XAMPP).
 *
 * $rows = list of rows. Each row is either:
 *   - a plain list of cells:            ['Date', 5, 'Name']
 *   - or ['cells' => [...], 'style' => 'title'|'header'|'total'|'section'|'meta']
 * A cell is a string, an int/float (numeric), or xl_money($amount) for peso amounts.
 */

function xl_money($amount)
{
    return ['money' => (float)$amount];
}

function xlsx_clean($text)
{
    // remove characters XML cannot hold
    $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string)$text);
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xlsx_col($index) // 0 => A, 25 => Z, 26 => AA
{
    $s = '';
    $index++;
    while ($index > 0) {
        $m = ($index - 1) % 26;
        $s = chr(65 + $m) . $s;
        $index = intdiv($index - $m - 1, 26);
    }
    return $s;
}

function xlsx_send(array $rows, $sheetName, $filename)
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    // Style ids (see styles.xml below): 0 normal, 1 bold, 2 header, 3 money, 4 bold money, 5 title
    $widths = [];
    $sheetRows = '';
    foreach ($rows as $i => $row) {
        $style = null;
        $cells = $row;
        if (isset($row['cells'])) {
            $cells = $row['cells'];
            $style = $row['style'] ?? null;
        }
        $r = $i + 1;
        $xml = '';
        foreach ($cells as $c => $cell) {
            if ($cell === '' || $cell === null) continue;
            $ref = xlsx_col($c) . $r;

            $s = 0;
            if ($style === 'title') $s = 5;
            elseif ($style === 'header') $s = 2;
            elseif ($style === 'total' || $style === 'section') $s = 1;
            elseif ($style === 'meta' && $c === 0) $s = 1;

            if (is_array($cell) && isset($cell['money'])) {
                $s = ($style === 'total') ? 4 : 3;
                $text = number_format($cell['money'], 2);
                $xml .= '<c r="' . $ref . '" s="' . $s . '"><v>' . $cell['money'] . '</v></c>';
            } elseif (is_int($cell) || is_float($cell)) {
                $text = (string)$cell;
                $xml .= '<c r="' . $ref . '" s="' . $s . '"><v>' . $cell . '</v></c>';
            } else {
                $text = (string)$cell;
                $xml .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">'
                    . xlsx_clean($text) . '</t></is></c>';
            }

            // Column width is based on table rows only (not the title / info lines at the top)
            if ($style !== 'title' && $style !== 'meta' && $style !== 'section') {
                $len = mb_strlen($text) + ($style === 'header' ? 2 : 0);
                $widths[$c] = max($widths[$c] ?? 10, $len);
            }
        }
        $sheetRows .= '<row r="' . $r . '">' . $xml . '</row>';
    }

    $cols = '';
    ksort($widths);
    foreach ($widths as $c => $w) {
        $w = min(60, $w + 3);
        $cols .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . ($cols ? '<cols>' . $cols . '</cols>' : '')
        . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;₱&quot;#,##0.00"/></numFmts>'
        . '<fonts count="4">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="14"/><color rgb="FF077F5A"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF077F5A"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="6">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $sheetName = substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $sheetName), 0, 31);

    $tmp = tempnam(sys_get_temp_dir(), 'ckx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        return false;
    }
    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . xlsx_clean($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    return true;
}
