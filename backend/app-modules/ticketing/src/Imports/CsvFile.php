<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Imports;

use Generator;
use Illuminate\Validation\ValidationException;

/**
 * Lê o CSV exportado por outra plataforma (Sympla, Even3, planilha...).
 *
 * Aceita UTF-8 (com ou sem BOM) e Windows-1252, que é o que o Excel em
 * português costuma salvar, e descobre sozinho se o separador é ponto e
 * vírgula, vírgula, tabulação ou barra. A leitura anda por posição em bytes
 * (`offset`), para a importação retomar exatamente de onde parou.
 */
final readonly class CsvFile
{
    public const array DELIMITERS = [';', ',', "\t", '|'];

    private function __construct(
        public string $path,
        public string $delimiter,
        public string $encoding,
    ) {}

    public static function open(string $path, ?string $delimiter = null, ?string $encoding = null): self
    {
        if (!is_readable($path)) {
            throw ValidationException::withMessages(['file' => 'Não consegui ler o arquivo.']);
        }

        $sample = (string) file_get_contents($path, false, null, 0, 65_536);

        if (mb_trim(self::stripBom($sample)) === '') {
            throw ValidationException::withMessages(['file' => 'O arquivo está vazio.']);
        }

        return new self(
            $path,
            $delimiter ?? self::guessDelimiter($sample),
            $encoding ?? (mb_check_encoding(self::cutIncompleteTail($sample), 'UTF-8') ? 'UTF-8' : 'Windows-1252'),
        );
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        foreach ($this->records(0) as $record) {
            return $record['values'];
        }

        return [];
    }

    /**
     * Posição em bytes logo depois do cabeçalho, onde começam os dados.
     */
    public function dataOffset(): int
    {
        foreach ($this->records(0) as $record) {
            return $record['next'];
        }

        return 0;
    }

    /**
     * Linhas de dados a partir de `$offset` (em bytes). `next` é a posição
     * da linha seguinte; `blank` marca linha sem nenhum valor.
     *
     * @return Generator<int, array{values: list<string>, next: int, blank: bool}>
     */
    public function records(int $offset): Generator
    {
        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            if ($offset === 0 && fread($handle, 3) !== "\xEF\xBB\xBF") {
                rewind($handle);
            } elseif ($offset > 0) {
                fseek($handle, $offset);
            }

            while (($row = fgetcsv($handle, null, $this->delimiter, '"', '')) !== false) {
                $values = array_map(fn (?string $value): string => $this->clean((string) $value), $row);

                yield [
                    'values' => $values,
                    'next' => (int) ftell($handle),
                    'blank' => implode('', $values) === '',
                ];
            }
        } finally {
            fclose($handle);
        }
    }

    public function countRows(): int
    {
        $count = 0;

        foreach ($this->records($this->dataOffset()) as $record) {
            if (!$record['blank']) {
                $count++;
            }
        }

        return $count;
    }

    private static function guessDelimiter(string $sample): string
    {
        $firstLine = strtok(self::stripBom($sample), "\r\n");
        $firstLine = $firstLine === false ? '' : (string) preg_replace('/"[^"]*"/', '', $firstLine);

        $counts = [];

        foreach (self::DELIMITERS as $delimiter) {
            $counts[$delimiter] = mb_substr_count($firstLine, $delimiter);
        }

        arsort($counts);
        $best = (string) array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    private static function stripBom(string $sample): string
    {
        return (string) preg_replace('/^\xEF\xBB\xBF/', '', $sample);
    }

    /**
     * A amostra de 64 KB pode cortar um caractere de vários bytes no meio,
     * o que faria um UTF-8 válido parecer inválido.
     */
    private static function cutIncompleteTail(string $sample): string
    {
        for ($cut = 0; $cut < 4 && $sample !== ''; $cut++) {
            if (mb_check_encoding($sample, 'UTF-8')) {
                return $sample;
            }

            // Corte em bytes, não em caracteres: a string ainda pode não ser UTF-8.
            $sample = mb_strcut($sample, 0, mb_strlen($sample, '8bit') - 1, '8bit');
        }

        return $sample;
    }

    private function clean(string $value): string
    {
        if ($this->encoding !== 'UTF-8') {
            $converted = mb_convert_encoding($value, 'UTF-8', $this->encoding);
            $value = is_string($converted) ? $converted : $value;
        }

        return mb_trim($value);
    }
}
