<?php
/**
 * MinimalXlsxWriter
 * ------------------
 * Genera archivos .xlsx (Excel) reales, válidos, sin ninguna librería
 * externa -- solo usa la extensión ZipArchive, que viene activada por
 * defecto en prácticamente cualquier hosting compartido con PHP
 * (incluido Hostinger Premium, sin necesidad de Composer ni SSH).
 *
 * Uso:
 *   $xlsx = new MinimalXlsxWriter();
 *   $xlsx->addSheet('Octubre 2026', ['Huésped','Entrada','Salida'], [
 *       ['Juan Pérez', '2026-10-05', '2026-10-09'],
 *       ['Ana López',  '2026-10-12', '2026-10-14'],
 *   ]);
 *   $xlsx->save('/ruta/reporte.xlsx');
 */
class MinimalXlsxWriter
{
    /** @var array<int, array{name:string, headers:string[], rows:array[]}> */
    private array $sheets = [];

    public function addSheet(string $name, array $headers, array $rows): void
    {
        // Excel no permite: \ / ? * [ ] : en el nombre de hoja, y máximo 31 caracteres.
        $clean = preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $name);
        $clean = mb_substr($clean, 0, 31);
        $this->sheets[] = ['name' => $clean, 'headers' => $headers, 'rows' => $rows];
    }

    public function save(string $path): bool
    {
        if (file_exists($path)) {
            @unlink($path);
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE) !== true) {
            return false;
        }

        $zip->addEmptyDir('_rels');
        $zip->addEmptyDir('xl');
        $zip->addEmptyDir('xl/_rels');
        $zip->addEmptyDir('xl/worksheets');

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->sheetXml($sheet));
        }

        $zip->close();
        return true;
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function colLetter(int $index): string
    {
        // 0 -> A, 1 -> B, ... 26 -> AA
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = intdiv($index - 1, 26);
        }
        return $letter;
    }

    private function contentTypesXml(): string
    {
        $overrides = '';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $overrides .= "<Override PartName=\"/xl/worksheets/sheet{$n}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        $sheetsXml = '';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $sheetsXml .= '<sheet name="' . $this->e($sheet['name']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetsXml . '</sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        $rels = '';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        }
        // El estilo va con el último Id disponible
        $styleId = count($this->sheets) + 1;
        $rels .= '<Relationship Id="rId' . $styleId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        // Estilo 0 = normal. Estilo 1 = encabezado (negritas + fondo).
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><sz val="11"/><name val="Calibri"/><b/><color rgb="FFFFFFFF"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF3B2F20"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function sheetXml(array $sheet): string
    {
        $headers = $sheet['headers'];
        $rows = $sheet['rows'];

        // Anchos de columna aproximados según el encabezado más largo de cada una.
        $cols = '<cols>';
        foreach ($headers as $i => $h) {
            $width = max(10, min(40, mb_strlen((string)$h) + 4));
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $width . '" customWidth="1"/>';
        }
        $cols .= '</cols>';

        $sheetData = '<row r="1">';
        foreach ($headers as $i => $h) {
            $ref = $this->colLetter($i) . '1';
            $sheetData .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t xml:space="preserve">' . $this->e((string)$h) . '</t></is></c>';
        }
        $sheetData .= '</row>';

        foreach ($rows as $rIdx => $row) {
            $r = $rIdx + 2; // fila 1 es el encabezado
            $sheetData .= '<row r="' . $r . '">';
            foreach ($row as $cIdx => $val) {
                $ref = $this->colLetter($cIdx) . $r;
                if ($val === null || $val === '') {
                    $sheetData .= '<c r="' . $ref . '"/>';
                } elseif (is_numeric($val) && !preg_match('/^0[0-9]/', (string)$val)) {
                    // Números reales (precios, noches). Evita tratar como número cosas como "01" (ej. folios).
                    $sheetData .= '<c r="' . $ref . '"><v>' . (0 + $val) . '</v></c>';
                } else {
                    $sheetData .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $this->e((string)$val) . '</t></is></c>';
                }
            }
            $sheetData .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $cols
            . '<sheetData>' . $sheetData . '</sheetData>'
            . '</worksheet>';
    }
}
