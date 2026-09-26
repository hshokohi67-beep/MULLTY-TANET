<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\ImportCatalogSheet;
use App\Modules\Catalog\Exceptions\CatalogImportException;
use App\Modules\Catalog\Http\Requests\CatalogImportRequest;
use App\Support\Export\XlsxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

/** «ورود از اکسل»: a blank template, a preview of what the file would change, and the import itself. */
final class CatalogImportController
{
    public function template(): Response
    {
        $bytes = XlsxWriter::build('منو', ['نام', 'دسته', 'قیمت (تومان)', 'توضیح', 'کالری'], [
            ['اسپرسو', 'قهوه‌ی گرم', 85000, 'دوبل، از دانه‌ی عربیکا', 10],
            ['آیس لاته', 'نوشیدنی سرد', 110000, 'اسپرسو، شیر و یخ', 170],
            ['چیزکیک', 'کیک و دسر', 145000, '', 410],
        ], [28, 22, 16, 40, 10]);

        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="menu-template.xlsx"',
        ]);
    }

    public function preview(CatalogImportRequest $request, ImportCatalogSheet $import): JsonResponse
    {
        $rows = $this->rows($request);
        $plan = $import->plan($rows, $request->columns($rows), $request->unit());

        return response()->json(['data' => [...$plan, 'header' => $rows[0], 'unit' => $request->unit()]]);
    }

    public function store(CatalogImportRequest $request, ImportCatalogSheet $import): JsonResponse
    {
        $rows = $this->rows($request);
        $columns = $request->columns($rows);
        if (! isset($columns['name'], $columns['price'])) {
            throw CatalogImportException::missingColumns();
        }

        return response()->json(['data' => $import->apply($rows, $columns, $request->unit(), (string) $request->user()?->getAuthIdentifier())]);
    }

    /** @return list<list<string>> */
    private function rows(CatalogImportRequest $request): array
    {
        try {
            $rows = $request->rows();
        } catch (RuntimeException) {
            throw CatalogImportException::unreadable();
        }
        if (count($rows) < 2) {
            throw CatalogImportException::empty();
        }

        return $rows;
    }
}
