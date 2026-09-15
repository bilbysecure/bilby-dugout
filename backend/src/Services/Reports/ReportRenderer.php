<?php

declare(strict_types=1);

namespace App\Services\Reports;

/**
 * Renders an assembled report to a document (Master Spec §15).
 *
 * ⚠️ The current binding is HtmlReportRenderer (branded HTML). A PdfReportRenderer
 * is a drop-in swap once a PDF library is approved (e.g. dompdf/dompdf) — it
 * satisfies this same contract, so no service/controller changes are needed.
 */
interface ReportRenderer
{
    /** @return string the rendered document bytes */
    public function render(array $report, array $data, array $branding): string;

    public function extension(): string;

    public function mimeType(): string;
}
