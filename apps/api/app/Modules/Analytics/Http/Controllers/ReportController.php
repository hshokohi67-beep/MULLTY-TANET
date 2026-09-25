<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Modules\Analytics\Http\Requests\ReportRequest;
use App\Modules\Analytics\Support\Metrics;
use App\Modules\Analytics\Support\Reports;
use App\Modules\Analytics\Support\ReportTables;
use App\Support\Export\XlsxWriter;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reports over any tenant-local date range (`reports.view`). */
final class ReportController
{
    public function summary(ReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports($request)->summary($request->compareMode())]);
    }

    public function products(ReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports($request)->products($request->string('sort', 'revenue')->toString())]);
    }

    public function hours(ReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports($request)->hours()]);
    }

    public function branches(ReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports($request)->branches()]);
    }

    public function customers(ReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports($request)->customers()]);
    }

    public function inventory(ReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports($request)->inventory()]);
    }

    public function export(ReportRequest $request, TenantContext $context): StreamedResponse
    {
        $tenant = $context->require();
        $period = $request->period();
        $report = $request->string('report', 'summary')->toString();
        $format = $request->string('format', 'xlsx')->toString();
        $compare = $request->compareMode();
        $branchId = $request->branchId();

        Metrics::refresh($period->fromDate(), $period->toDate());
        $name = "report-{$report}-{$period->fromDate()}_{$period->toDate()}.{$format}";

        // The body is produced after the middleware cleared the tenant context: re-enter it.
        return response()->streamDownload(fn () => $context->runAs($tenant, function () use ($period, $branchId, $report, $format, $compare): void {
            $table = (new ReportTables(new Reports($period, $branchId, CarbonImmutable::now()), $period))->table($report, $compare);

            if ($format === 'xlsx') {
                echo XlsxWriter::build($table['title'], $table['headers'], $table['rows'], $table['widths']);

                return;
            }

            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF"); // BOM, so Excel opens Persian text as UTF-8
            fputcsv($out, $table['headers'], ',', '"', '');
            foreach ($table['rows'] as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }), $name, ['Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8']);
    }

    private function reports(ReportRequest $request): Reports
    {
        return new Reports($request->period(), $request->branchId(), CarbonImmutable::now());
    }
}
