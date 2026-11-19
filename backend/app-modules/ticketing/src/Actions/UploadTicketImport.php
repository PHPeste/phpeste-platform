<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Enums\ImportStatus;
use PHPeste\Ticketing\Imports\ColumnGuesser;
use PHPeste\Ticketing\Imports\CsvFile;
use PHPeste\Ticketing\Imports\ImportPreview;
use PHPeste\Ticketing\Imports\RowParser;
use PHPeste\Ticketing\Models\TicketImport;
use PHPeste\Ticketing\Models\TicketType;

/**
 * Guarda o CSV no disco privado, lê o cabeçalho e sugere o mapeamento.
 * Nada é importado aqui.
 */
class UploadTicketImport
{
    public function __construct(
        private readonly ColumnGuesser $guesser,
        private readonly ImportPreview $preview,
    ) {}

    public function handle(string $editionId, UploadedFile $upload, string $platform, ?string $actorId): TicketImport
    {
        $disk = Storage::disk(ProcessImportChunk::disk());
        $path = (string) $disk->putFileAs('ticket-imports/'.$editionId, $upload, Str::uuid7()->toString().'.csv');

        try {
            $file = CsvFile::open($disk->path($path));
            $headers = $file->headers();

            if (count(array_filter($headers, fn (string $header): bool => $header !== '')) < 2) {
                throw ValidationException::withMessages(['file' => 'Não encontrei o cabeçalho. A primeira linha do arquivo precisa ter o nome de cada coluna.']);
            }

            $rows = $file->countRows();
            $max = (int) config('ticketing.imports.max_rows', 20_000);

            if ($rows === 0) {
                throw ValidationException::withMessages(['file' => 'O arquivo só tem o cabeçalho, sem nenhum participante.']);
            }

            if ($rows > $max) {
                throw ValidationException::withMessages(['file' => sprintf('O arquivo tem %d linhas; o máximo por importação é %d. Divida em partes.', $rows, $max)]);
            }
        } catch (ValidationException $validationException) {
            $disk->delete($path);

            throw $validationException;
        }

        $import = TicketImport::create([
            'edition_id' => $editionId,
            'created_by' => $actorId,
            'platform' => $platform,
            'status' => ImportStatus::Uploaded,
            'file_name' => mb_substr($upload->getClientOriginalName(), 0, 191),
            'file_path' => $path,
            'delimiter' => $file->delimiter,
            'encoding' => $file->encoding,
            'headers' => $headers,
            'mapping' => $this->guesser->guess($headers),
            'total_rows' => $rows,
        ]);

        $import->update(['ticket_type_map' => $this->suggestTypes($import)]);

        return $import;
    }

    /**
     * Valor da coluna de tipo com o mesmo nome de um tipo desta edição já
     * vem ligado a ele; o resto fica para quem importa escolher.
     *
     * @return array<string, string>
     */
    private function suggestTypes(TicketImport $import): array
    {
        $types = TicketType::query()->forEdition($import->edition_id)->get(['id', 'name'])
            ->mapWithKeys(fn (TicketType $type): array => [RowParser::typeKey($type->name) => $type->id]);
        $suggested = [];

        foreach (array_keys($this->preview->ticketTypeValues($import)) as $key) {
            if ($types->has($key)) {
                $suggested[$key] = (string) $types->get($key);
            }
        }

        return $suggested;
    }
}
