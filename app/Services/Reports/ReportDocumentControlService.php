<?php

namespace App\Services\Reports;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class ReportDocumentControlService
{
    public function apply(array $report, ?User $generatedBy = null): array
    {
        $timezone = (string) config('tupad_reports.document.timezone', 'Asia/Manila');
        $generatedAt = $report['generated_at'] ?? now($timezone);

        if (! $generatedAt instanceof CarbonInterface) {
            $generatedAt = now($timezone);
        } else {
            $generatedAt = $generatedAt->copy()->timezone($timezone);
        }

        $version = trim((string) config('tupad_reports.document.version', '1.0'));
        $revision = trim((string) config('tupad_reports.document.revision', '0'));
        $classification = trim((string) config(
            'tupad_reports.document.classification',
            'Internal Government Report'
        ));
        $office = trim((string) config(
            'tupad_reports.document.office',
            'Department of Labor and Employment - Regional Office V'
        ));
        $prefix = trim((string) config(
            'tupad_reports.document.reference_prefix',
            'DOLE-RO5-TUPAD'
        ));

        $identity = trim((string) ($report['official_code'] ?? $report['title'] ?? 'REPORT'));
        $identity = Str::upper(Str::slug($identity, '-'));
        $identity = Str::limit($identity, 36, '');

        $reference = (string) data_get($report, 'document_control.reference', '');
        if ($reference === '') {
            $reference = implode('-', array_filter([
                $prefix,
                $identity,
                $generatedAt->format('Ymd-His'),
            ]));
        }

        $report['generated_at'] = $generatedAt;
        $report['document_control'] = [
            'version' => $version !== '' ? $version : '1.0',
            'revision' => $revision !== '' ? $revision : '0',
            'version_label' => sprintf(
                'v%s · Rev %s',
                $version !== '' ? $version : '1.0',
                $revision !== '' ? $revision : '0',
            ),
            'classification' => $classification !== ''
                ? $classification
                : 'Internal Government Report',
            'reference' => $reference,
            'office' => $office,
            'generated_at' => $generatedAt,
            'generated_by' => $generatedBy?->name ?: 'System-generated',
            'generated_by_username' => $generatedBy?->username,
        ];
        $report['signatories'] = $this->signatories();

        return $report;
    }

    private function signatories(): array
    {
        return collect((array) config('tupad_reports.signatories', []))
            ->map(function (array $signatory, string $key): array {
                $name = trim((string) ($signatory['name'] ?? ''));
                $position = trim((string) ($signatory['position'] ?? ''));
                $office = trim((string) ($signatory['office'] ?? ''));

                return [
                    'key' => $key,
                    'label' => trim((string) ($signatory['label'] ?? Str::headline($key))),
                    'name' => $name,
                    'display_name' => $name,
                    'position' => $position,
                    'display_position' => $position !== '' ? $position : 'Name / Signature',
                    'office' => $office,
                ];
            })
            ->values()
            ->all();
    }
}
