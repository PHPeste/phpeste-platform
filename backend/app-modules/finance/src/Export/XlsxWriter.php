<?php

declare(strict_types=1);

namespace PHPeste\Finance\Export;

use RuntimeException;
use ZipArchive;

/**
 * Gera um .xlsx mínimo (Office Open XML) com várias abas, sem biblioteca
 * externa: texto vai como inline string e número como número, então o
 * Excel e o LibreOffice somam as colunas de valor.
 */
final class XlsxWriter
{
    /**
     * @param  array<string, list<list<string|int|float|null>>>  $sheets  nome da aba => linhas (a primeira é o cabeçalho)
     */
    public static function write(array $sheets, string $path): void
    {
        throw_unless(class_exists(ZipArchive::class), RuntimeException::class, 'A extensão zip do PHP é necessária para gerar XLSX.');

        $zip = new ZipArchive;

        throw_if($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true, RuntimeException::class, 'Não consegui criar o arquivo XLSX.');

        $names = array_keys($sheets);
        $count = count($names);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .implode('', array_map(fn (int $i): string => sprintf('<Override PartName="/xl/worksheets/sheet%d.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', $i), range(1, $count)))
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
            .implode('', array_map(fn (int $i): string => sprintf('<sheet name="%s" sheetId="%d" r:id="rId%d"/>', self::escape(mb_substr($names[$i - 1], 0, 31)), $i, $i), range(1, $count)))
            .'</sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .implode('', array_map(fn (int $i): string => sprintf('<Relationship Id="rId%d" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet%d.xml"/>', $i, $i), range(1, $count)))
            .sprintf('<Relationship Id="rId%d" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>', $count + 1)
            .'</Relationships>');

        // Estilo 1: negrito (cabeçalho). Estilo 2: número com 2 casas e milhar.
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'</styleSheet>');

        foreach ($names as $index => $name) {
            $zip->addFromString(sprintf('xl/worksheets/sheet%d.xml', $index + 1), self::sheet($sheets[$name]));
        }

        $zip->close();
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    private static function sheet(array $rows): string
    {
        $xml = '';

        foreach ($rows as $r => $row) {
            $xml .= sprintf('<row r="%d">', $r + 1);

            foreach ($row as $c => $value) {
                $ref = self::column($c).($r + 1);

                if ($value === null || $value === '') {
                    continue;
                }

                if (is_int($value) || is_float($value)) {
                    $xml .= sprintf('<c r="%s"%s><v>%s</v></c>', $ref, is_float($value) ? ' s="2"' : '', $value);
                } else {
                    $xml .= sprintf('<c r="%s" t="inlineStr"%s><is><t xml:space="preserve">%s</t></is></c>', $ref, $r === 0 ? ' s="1"' : '', self::escape($value));
                }
            }

            $xml .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xml.'</sheetData></worksheet>';
    }

    private static function column(int $index): string
    {
        $name = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + (($index - 1) % 26)).$name;
        }

        return $name;
    }

    private static function escape(string $value): string
    {
        // Remove caracteres de controle que o XML não aceita.
        $clean = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
