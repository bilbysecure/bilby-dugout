<?php

declare(strict_types=1);

namespace App\Services\Reports;

/**
 * Branded HTML report (Master Spec §15). Colours/logo/brand-name come from the
 * resolved branding (BilbyPixel default or client white-label). The section body
 * is data-driven, so the shell renders even when a metric source is thin.
 *
 * Swapping in a PDF renderer later is a DI change only.
 */
final class HtmlReportRenderer implements ReportRenderer
{
    private const TITLES = [
        'social_performance' => 'Social Performance Report',
        'campaign_wrapup'    => 'Ad Campaign Wrap-up',
        'delivery_summary'   => 'Delivery Summary',
    ];

    public function extension(): string
    {
        return 'html';
    }

    public function mimeType(): string
    {
        return 'text/html';
    }

    public function render(array $report, array $data, array $branding): string
    {
        $primary = $this->safeColour($branding['primary'] ?? '#4B2A85');
        $title = self::TITLES[$report['type'] ?? ''] ?? 'Report';
        $period = $this->period($report);
        $brandName = $this->esc((string) ($branding['brand_name'] ?? 'BilbyPixel'));
        $logo = !empty($branding['logo_url'])
            ? '<img src="' . $this->esc((string) $branding['logo_url']) . '" alt="" style="height:44px">'
            : '<span class="wordmark">' . $brandName . '</span>';

        $body = $this->sectionBody($data);
        $footer = $this->esc((string) ($branding['footer'] ?? 'Prepared by BilbyPixel'));
        $generated = date('j M Y');

        return <<<HTML
<!doctype html>
<html><head><meta charset="utf-8"><title>{$this->esc($title)}</title>
<style>
  body { font-family: system-ui, Arial, sans-serif; color: #1c2430; margin: 0; }
  .head { background: {$primary}; color: #fff; padding: 28px 36px; display: flex; align-items: center; justify-content: space-between; }
  .head h1 { margin: 6px 0 0; font-size: 22px; }
  .wordmark { font-weight: 800; font-size: 20px; letter-spacing: .02em; }
  .period { opacity: .85; font-size: 13px; }
  .content { padding: 28px 36px; }
  .section-title { color: {$primary}; font-size: 16px; border-bottom: 2px solid {$primary}; padding-bottom: 6px; }
  .grid { display: flex; flex-wrap: wrap; gap: 18px; margin: 16px 0; }
  .stat { min-width: 120px; }
  .stat .n { font-size: 26px; font-weight: 700; }
  .stat .l { font-size: 11px; text-transform: uppercase; color: #6b7480; letter-spacing: .04em; }
  table { border-collapse: collapse; width: 100%; margin-top: 8px; }
  td, th { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e3e6ea; font-size: 13px; }
  .foot { padding: 16px 36px; color: #6b7480; font-size: 12px; border-top: 1px solid #e3e6ea; }
</style></head>
<body>
  <div class="head"><div><div>{$logo}</div><h1>{$this->esc($title)}</h1><div class="period">{$period}</div></div></div>
  <div class="content">{$body}</div>
  <div class="foot">{$footer} · Generated {$generated}</div>
</body></html>
HTML;
    }

    private function sectionBody(array $data): string
    {
        $section = $this->esc((string) ($data['section'] ?? 'Summary'));
        $html = "<h2 class=\"section-title\">{$section}</h2>";

        // Headline stat grid from any scalar/int fields.
        $stats = [];
        foreach ($data as $key => $value) {
            if (is_int($value) || is_float($value)) {
                $stats[] = '<div class="stat"><span class="n">' . number_format((float) $value, is_float($value) ? 2 : 0)
                    . '</span><span class="l">' . $this->esc(str_replace('_', ' ', (string) $key)) . '</span></div>';
            }
        }
        if ($stats !== []) {
            $html .= '<div class="grid">' . implode('', $stats) . '</div>';
        }

        // Nested breakdown tables.
        foreach (['totals', 'by_platform', 'by_type'] as $key) {
            if (!empty($data[$key]) && is_array($data[$key])) {
                $rows = '';
                foreach ($data[$key] as $k => $v) {
                    $rows .= '<tr><td>' . $this->esc(str_replace('_', ' ', (string) $k)) . '</td><td>' . $this->esc((string) $v) . '</td></tr>';
                }
                $html .= '<h3>' . $this->esc(ucfirst(str_replace('_', ' ', $key))) . '</h3><table>' . $rows . '</table>';
            }
        }

        if ($stats === [] && empty($data['totals'])) {
            $html .= '<p style="color:#6b7480">No data for this period yet — this report will enrich automatically as results accrue.</p>';
        }
        return $html;
    }

    private function period(array $report): string
    {
        $s = $report['period_start'] ?? null;
        $e = $report['period_end'] ?? null;
        return $s && $e ? "{$s} – {$e}" : 'All time';
    }

    private function safeColour(string $c): string
    {
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $c) ? $c : '#4B2A85';
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
