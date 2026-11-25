<?php

namespace App\Services\Dashboards;

use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Models\AuditLog;
use App\Models\BeneficiaryTarget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FocalDashboardOverviewService
{
    public const STATUS_COLORS = [
        'tssd_evaluation' => '#4f46e5',            // indigo-600
        'for_compliance' => '#f59e0b',             // amber-500
        'for_approval' => '#818cf8',               // indigo-400
        'approved' => '#0284c7',                   // sky-600
        'for_implementation' => '#38bdf8',         // sky-400
        'ongoing_implementation' => '#7c3aed',     // violet-600
        'for_submission_of_post_docs' => '#f97316', // orange-500
        'for_payment' => '#0d9488',                // teal-600
        'for_release_of_check_to_proponent' => '#2dd4bf', // teal-400
        'for_liquidation' => '#e11d48',            // rose-600
        'partially_liquidated' => '#fb7185',       // rose-400
        'completed' => '#059669',                  // emerald-600
    ]; 

    public function build(
        Builder $projects,
        array $fund,
        int $endingSoonDays = 7,
        ?int $targetYear = null,
        ?Builder $targetProjects = null,
        ?int $activityUserId = null,
    ): array {
        $targetYear ??= (int) now()->year;

        return [
            'finance' => $this->finance($projects, $fund),
            'status' => $this->status($projects),
            'beneficiaries' => $this->beneficiaries($projects)
                + ['targets' => $this->beneficiaryTargets($targetYear, $targetProjects ?? (clone $projects)->whereYear('projects.date_received', $targetYear))],
            'implementation' => $this->implementation($projects, $endingSoonDays),
            'activity' => $this->activity($activityUserId),
        ];
    }

    private function finance(Builder $projects, array $fund): array
    {
        $projectIds = (clone $projects)->select('projects.id');

        $obligated = (float) DB::table('project_obligations')
            ->whereIn('project_id', $projectIds)
            ->sum('amount');

        // Paid = Direct Administration disbursements + Through ACP payments.
        $disbursed = (float) DB::table('project_disbursements')
            ->join('project_obligations', 'project_obligations.id', '=', 'project_disbursements.project_obligation_id')
            ->whereIn('project_obligations.project_id', (clone $projects)->select('projects.id'))
            ->sum('project_disbursements.amount');

        $acpPaid = (float) DB::table('project_acp_payments')
            ->whereIn('project_id', (clone $projects)->select('projects.id'))
            ->sum('amount');

        $budget = (float) $fund['budget'];

        return [
            'budget' => $budget,
            'allocated' => (float) $fund['allocated'],
            'obligated' => $obligated,
            'paid' => $disbursed + $acpPaid,
            'balance' => (float) $fund['remaining'],
            'utilization' => (float) $fund['utilization'],
            'adl_count' => $fund['adl_count'] ?? null,
        ];
    }

    private function status(Builder $projects): array
    {
        $counts = (clone $projects)
            ->reorder()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();

        $rows = collect(ProjectStatus::cases())
            ->map(function (ProjectStatus $status) use ($counts, $total) {
                $count = (int) ($counts[$status->value] ?? 0);

                return [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'count' => $count,
                    'percent' => $total > 0 ? $count / $total * 100 : 0.0,
                    'color' => self::STATUS_COLORS[$status->value],
                ];
            })
            ->values()
            ->all();

        return [
            'total' => $total,
            'rows' => $rows,
            'gradient' => $this->conicGradient($rows, $total),
        ];
    }

    private function beneficiaries(Builder $projects): array
    {
        $totals = (clone $projects)
            ->reorder()
            ->selectRaw('coalesce(sum(beneficiaries_total), 0) as total, coalesce(sum(beneficiaries_female), 0) as female')
            ->first();

        $byTerm = (clone $projects)
            ->reorder()
            ->select('term', DB::raw('coalesce(sum(beneficiaries_total), 0) as aggregate'))
            ->groupBy('term')
            ->pluck('aggregate', 'term');

        $total = (int) ($totals->total ?? 0);
        $female = (int) ($totals->female ?? 0);

        return [
            'total' => $total,
            'female' => $female,
            'male' => max(0, $total - $female),
            'short_term' => (int) ($byTerm[ProjectTerm::SHORT_TERM->value] ?? 0),
            'long_term' => (int) ($byTerm[ProjectTerm::LONG_TERM->value] ?? 0),
        ];
    }

    /**
     * @return array{year:int, terms: array<string, array{target:?int, achieved:int, balance:?int, percent:float}>}
     */
    private function beneficiaryTargets(int $year, Builder $yearProjects): array
    {
        $saved = BeneficiaryTarget::forYear($year);

        $achieved = (clone $yearProjects)
            ->reorder()
            ->select('term', DB::raw('coalesce(sum(beneficiaries_total), 0) as aggregate'))
            ->groupBy('term')
            ->pluck('aggregate', 'term');

        $terms = [];

        foreach (ProjectTerm::cases() as $term) {
            $target = $saved[$term->value] ?? null;
            $done = (int) ($achieved[$term->value] ?? 0);

            $terms[$term->value] = [
                'target' => $target,
                'achieved' => $done,
                'balance' => $target === null ? null : max(0, $target - $done),
                'percent' => $target ? min(100, $done / $target * 100) : 0.0,
            ];
        }

        return ['year' => $year, 'terms' => $terms];
    }

    private function implementation(Builder $projects, int $endingSoonDays): array
    {
        $today = Carbon::today();

        $ongoing = (clone $projects)
            ->where('status', ProjectStatus::ONGOING_IMPLEMENTATION);

        $withEndDate = fn () => (clone $ongoing)
            ->whereHas('implementation', fn ($query) => $query->whereNotNull('end_date'));

        $ongoingCount = (clone $ongoing)->count();

        $endingSoon = $withEndDate()
            ->whereHas('implementation', fn ($query) => $query
                ->whereDate('end_date', '>=', $today)
                ->whereDate('end_date', '<=', $today->copy()->addDays($endingSoonDays)))
            ->count();

        $postDocs = (clone $projects)
            ->where('status', ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS)
            ->count();

        return [
            'ongoing' => $ongoingCount,
            'ending_soon' => $endingSoon,
            'post_docs' => $postDocs,
            'ending_soon_days' => $endingSoonDays,
        ];
    }

    /** $userId limits the list to one user's own actions (TUPAD Coordinator). */
    private function activity(?int $userId = null): array
    {
        return AuditLog::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->with('user:id,name')
            ->latest('performed_at')
            ->limit(5)
            ->get()
            ->map(fn (AuditLog $log) => [
                'title' => trim(($log->module ?: 'Record').' '.str($log->action ?: 'updated')->lower()),
                'by' => $log->user?->name ?? 'System',
                'when' => $log->performed_at,
                'tone' => match (strtolower((string) $log->action)) {
                    'created' => 'emerald',
                    'deleted' => 'rose',
                    default => 'blue',
                },
            ])
            ->all();
    }

    private function conicGradient(array $rows, int $total): string
    {
        if ($total <= 0) {
            return '#e2e8f0';
        }

        $stops = [];
        $start = 0.0;

        foreach ($rows as $row) {
            if ($row['count'] === 0) {
                continue;
            }

            $end = $start + $row['count'] / $total * 100;
            $stops[] = sprintf('%s %.3f%% %.3f%%', $row['color'], $start, $end);
            $start = $end;
        }

        return 'conic-gradient('.implode(', ', $stops).')';
    }
}
