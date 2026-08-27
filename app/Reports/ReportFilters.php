<?php

namespace App\Reports;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectInterventionFocus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ReportFilters
{
    public function __construct(
        public ?CarbonImmutable $dateFrom = null,
        public ?CarbonImmutable $dateTo = null,
        public ?int $fiscalYear = null,
        public ?int $quarter = null,
        public ?int $month = null,
        public ?ProjectTerm $term = null,
        public ?ProjectStatus $status = null,
        public ?int $adlId = null,
        public ?int $provinceId = null,
        public ?string $district = null,
        public ?int $municipalityId = null,
        public ?int $barangayId = null,
        public ?string $sponsor = null,
        public ?string $partner = null,
        public ?string $projectCode = null,
        public ?BeneficiarySectorCategory $sector = null,
        public ?ProjectInterventionFocus $interventionFocus = null,
        public ?LaborMarketProgram $laborMarketProgram = null,
    ) {
        if ($this->dateFrom && $this->dateTo && $this->dateTo->lt($this->dateFrom)) {
            throw new InvalidArgumentException(
                'The reporting end date cannot precede the start date.'
            );
        }

        if ($this->fiscalYear !== null && ($this->fiscalYear < 2000 || $this->fiscalYear > 2100)) {
            throw new InvalidArgumentException(
                'Fiscal year must be between 2000 and 2100.'
            );
        }

        if ($this->quarter !== null && ($this->quarter < 1 || $this->quarter > 4)) {
            throw new InvalidArgumentException(
                'Quarter must be between 1 and 4.'
            );
        }

        if ($this->month !== null && ($this->month < 1 || $this->month > 12)) {
            throw new InvalidArgumentException(
                'Month must be between 1 and 12.'
            );
        }

        if (($this->quarter !== null || $this->month !== null) && $this->fiscalYear === null) {
            throw new InvalidArgumentException(
                'Fiscal year is required when filtering by quarter or month.'
            );
        }

        if ($this->quarter !== null && $this->month !== null) {
            throw new InvalidArgumentException(
                'Use either a reporting quarter or a reporting month, not both.'
            );
        }
    }

    public static function fromArray(array $filters): self
    {
        return new self(
            dateFrom: self::date($filters['date_from'] ?? null),
            dateTo: self::date($filters['date_to'] ?? null),
            fiscalYear: self::integer($filters['fiscal_year'] ?? null),
            quarter: self::integer($filters['quarter'] ?? null),
            month: self::integer($filters['month'] ?? null),
            term: self::resolveEnum(
                ProjectTerm::class,
                $filters['term'] ?? null,
            ),
            status: self::resolveEnum(
                ProjectStatus::class,
                $filters['status'] ?? null,
            ),
            adlId: self::integer($filters['adl_id'] ?? null),
            provinceId: self::integer($filters['province_id'] ?? null),
            district: self::text($filters['district'] ?? null),
            municipalityId: self::integer($filters['municipality_id'] ?? null),
            barangayId: self::integer($filters['barangay_id'] ?? null),
            sponsor: self::text($filters['sponsor'] ?? null),
            partner: self::text($filters['partner'] ?? null),
            projectCode: self::text($filters['project_code'] ?? null),
            sector: self::resolveEnum(
                BeneficiarySectorCategory::class,
                $filters['sector'] ?? null,
            ),
            interventionFocus: self::resolveEnum(
                ProjectInterventionFocus::class,
                $filters['intervention_focus'] ?? null,
            ),
            laborMarketProgram: self::resolveEnum(
                LaborMarketProgram::class,
                $filters['labor_market_program'] ?? null,
            ),
        );
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} */
    public function periodBounds(): array
    {
        $periodStart = null;
        $periodEnd = null;

        if ($this->fiscalYear !== null) {
            if ($this->month !== null) {
                $periodStart = CarbonImmutable::create(
                    $this->fiscalYear,
                    $this->month,
                    1,
                )->startOfDay();
                $periodEnd = $periodStart->endOfMonth();
            } elseif ($this->quarter !== null) {
                $periodStart = CarbonImmutable::create(
                    $this->fiscalYear,
                    (($this->quarter - 1) * 3) + 1,
                    1,
                )->startOfDay();
                $periodEnd = $periodStart->addMonths(2)->endOfMonth();
            } else {
                $periodStart = CarbonImmutable::create(
                    $this->fiscalYear,
                    1,
                    1,
                )->startOfDay();
                $periodEnd = $periodStart->endOfYear();
            }
        }

        if ($this->dateFrom && (!$periodStart || $this->dateFrom->gt($periodStart))) {
            $periodStart = $this->dateFrom->startOfDay();
        }

        if ($this->dateTo && (!$periodEnd || $this->dateTo->lt($periodEnd))) {
            $periodEnd = $this->dateTo->endOfDay();
        }

        return [$periodStart, $periodEnd];
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        return CarbonImmutable::parse((string) $value);
    }

    private static function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Reporting identifiers and periods must be integers.');
        }

        return (int) $value;
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @template TEnum of \BackedEnum
     *
     * @param class-string<TEnum> $enumClass
     * @return TEnum|null
     */
    private static function resolveEnum(
        string $enumClass,
        mixed $value,
    ): ?\BackedEnum
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $enumClass) {
            return $value;
        }

        $resolved = $enumClass::tryFrom((string) $value);

        if (! $resolved) {
            throw new InvalidArgumentException(
                sprintf('Invalid reporting filter value for %s.', $enumClass)
            );
        }

        return $resolved;
    }
}
