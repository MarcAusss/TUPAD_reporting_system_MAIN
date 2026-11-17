<?php

namespace App\Services\Exports;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Exports a report as a PDF that looks exactly like its Print page
 * (resources/views/reports/print.blade.php): the DOLE letterhead, title,
 * table and signatories, on the same page size and orientation.
 *
 * The print page is rendered with headless Chrome/Edge. When no browser is
 * available (or the engine is set to "native"), the built-in PdfTableWriter
 * is used so the export never fails.
 */
class ReportPdfExporter
{
    public function __construct(
        private readonly PdfTableWriter $nativeWriter,
    ) {}

    public function render(array $report): string
    {
        if (config('tupad_reports.pdf.engine', 'chrome') === 'chrome' && ($browser = $this->browserPath()) !== null) {
            try {
                return $this->renderWithBrowser($report, $browser);
            } catch (Throwable $exception) {
                Log::warning('Browser PDF export failed; using the built-in PDF writer.', [
                    'report' => $report['title'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $this->nativeWriter->render($report);
    }

    private function renderWithBrowser(array $report, string $browser): string
    {
        $this->sweepStaleWorkDirs();

        $workDir = storage_path('app/pdf-export/'.Str::uuid());
        File::ensureDirectoryExists($workDir);

        try {
            $htmlPath = $workDir.DIRECTORY_SEPARATOR.'report.html';
            $pdfPath = $workDir.DIRECTORY_SEPARATOR.'report.pdf';

            File::put($htmlPath, $this->printHtml($report));

            $result = Process::timeout((int) config('tupad_reports.pdf.timeout', 90))->run([
                $browser,
                '--headless=new',
                '--disable-gpu',
                '--no-first-run',
                '--disable-extensions',
                '--no-pdf-header-footer',
                '--allow-file-access-from-files',
                '--user-data-dir='.$workDir.DIRECTORY_SEPARATOR.'profile',
                '--print-to-pdf='.$pdfPath,
                $this->fileUrl($htmlPath),
            ]);

            if (! File::exists($pdfPath) || File::size($pdfPath) === 0) {
                throw new \RuntimeException('The browser did not produce a PDF. '.trim($result->errorOutput()));
            }

            return File::get($pdfPath);
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * A browser that is slow to exit can keep its profile locked, so the
     * per-export folder is sometimes left behind; remove any older than an hour.
     */
    private function sweepStaleWorkDirs(): void
    {
        $root = storage_path('app/pdf-export');

        if (! File::isDirectory($root)) {
            return;
        }

        foreach (File::directories($root) as $directory) {
            if (filemtime($directory) < time() - 3600) {
                File::deleteDirectory($directory);
            }
        }
    }

    /**
     * The Print page's HTML, with its images pointed at local files so the
     * browser can load them without going through the web server.
     */
    private function printHtml(array $report): string
    {
        $html = view('reports.print', ['report' => $report])->render();

        return str_replace(
            rtrim(asset('images'), '/').'/',
            $this->fileUrl(public_path('images')).'/',
            $html,
        );
    }

    private function fileUrl(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        $segments = array_map(
            // Keep a Windows drive letter ("C:") as is; encode spaces etc. elsewhere.
            fn (string $segment): string => preg_match('/^[A-Za-z]:$/', $segment) ? $segment : rawurlencode($segment),
            explode('/', $normalized),
        );

        return 'file:///'.ltrim(implode('/', $segments), '/');
    }

    private function browserPath(): ?string
    {
        $candidates = array_filter([
            config('tupad_reports.pdf.browser_path'),
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
