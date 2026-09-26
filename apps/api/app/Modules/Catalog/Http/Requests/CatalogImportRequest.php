<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Support\CatalogSheet;
use App\Support\Import\SpreadsheetReader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/** A menu spreadsheet (.xlsx or .csv, ≤ 2 MB), the price unit, and optionally the chosen columns. */
final class CatalogImportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:2048', 'extensions:xlsx,csv,txt'],
            'unit' => ['sometimes', Rule::in(['toman', 'rial'])],
            'columns' => ['sometimes', 'array:'.implode(',', CatalogSheet::FIELDS)],
            'columns.*' => ['nullable', 'integer', 'min:0', 'max:'.(SpreadsheetReader::MAX_COLS - 1)],
        ];
    }

    /** @return list<list<string>> */
    public function rows(): array
    {
        $file = $this->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        return SpreadsheetReader::read((string) $file->getRealPath(), strtolower($file->getClientOriginalExtension()));
    }

    /**
     * The columns the user chose, else the guess from the header row.
     *
     * @param  list<list<string>>  $rows
     * @return array<string, int>
     */
    public function columns(array $rows): array
    {
        $given = array_filter((array) $this->validated('columns', []), fn ($v) => $v !== null && $v !== '');

        return $given !== [] ? array_map('intval', $given) : CatalogSheet::guessColumns($rows[0] ?? []);
    }

    public function unit(): string
    {
        return (string) $this->validated('unit', 'toman');
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['file' => 'فایل'];
    }
}
