<?php

namespace App\Enums;

enum ReportType: string
{
    case PHYSICAL_FINANCIAL = 'physical_financial';
    case FUND_STATUS = 'fund_status';
    case GEOGRAPHIC_BENEFICIARIES = 'geographic_beneficiaries';
    case BENEFICIARY_SECTORS = 'beneficiary_sectors';
    case INTERVENTION_FOCUS = 'intervention_focus';
    case LABOR_MARKET_REFERRALS = 'labor_market_referrals';

    public function label(): string
    {
        return match ($this) {
            self::PHYSICAL_FINANCIAL =>
                'Physical and Financial Accomplishment',
            self::FUND_STATUS => 'Fund Status',
            self::GEOGRAPHIC_BENEFICIARIES =>
                'Geographic Beneficiary Accomplishment',
            self::BENEFICIARY_SECTORS =>
                'Beneficiary Sector Classification',
            self::INTERVENTION_FOCUS =>
                'Intervention-Focus Classification',
            self::LABOR_MARKET_REFERRALS =>
                'Active Labor Market Program Referrals',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PHYSICAL_FINANCIAL =>
                'Project, beneficiary, cost, obligation, and disbursement accomplishment.',
            self::FUND_STATUS =>
                'TUPAD allocation, payable wages, obligations, disbursements, and balances.',
            self::GEOGRAPHIC_BENEFICIARIES =>
                'Exact beneficiary allocations by official project geography.',
            self::BENEFICIARY_SECTORS =>
                'Priority, vulnerable, occupational, and livelihood sector statistics.',
            self::INTERVENTION_FOCUS =>
                'Projects and beneficiaries by primary intervention focus.',
            self::LABOR_MARKET_REFERRALS =>
                'Referrals, intervention recipients, female counts, services, and amounts released.',
        };
    }

    public function defaultDimension(): ReportDimension
    {
        return match ($this) {
            self::PHYSICAL_FINANCIAL => ReportDimension::OVERALL,
            self::FUND_STATUS => ReportDimension::ADL,
            self::GEOGRAPHIC_BENEFICIARIES => ReportDimension::PROVINCE,
            self::BENEFICIARY_SECTORS => ReportDimension::SECTOR,
            self::INTERVENTION_FOCUS => ReportDimension::INTERVENTION_FOCUS,
            self::LABOR_MARKET_REFERRALS =>
                ReportDimension::LABOR_MARKET_PROGRAM,
        };
    }

    /** @return array<int, ReportDimension> */
    public function allowedDimensions(): array
    {
        $projectDimensions = [
            ReportDimension::OVERALL,
            ReportDimension::MONTH,
            ReportDimension::QUARTER,
            ReportDimension::FISCAL_YEAR,
            ReportDimension::TERM,
            ReportDimension::ADL,
            ReportDimension::PROVINCE,
            ReportDimension::DISTRICT,
            ReportDimension::MUNICIPALITY,
            ReportDimension::BARANGAY,
            ReportDimension::STATUS,
            ReportDimension::SPONSOR,
            ReportDimension::PARTNER,
            ReportDimension::PROJECT_CODE,
        ];

        $safeClassificationDimensions = [
            ReportDimension::OVERALL,
            ReportDimension::MONTH,
            ReportDimension::QUARTER,
            ReportDimension::FISCAL_YEAR,
            ReportDimension::TERM,
            ReportDimension::ADL,
            ReportDimension::PROVINCE,
            ReportDimension::STATUS,
            ReportDimension::SPONSOR,
            ReportDimension::PARTNER,
            ReportDimension::PROJECT_CODE,
            ReportDimension::INTERVENTION_FOCUS,
        ];

        return match ($this) {
            self::PHYSICAL_FINANCIAL,
            self::FUND_STATUS => $projectDimensions,
            self::GEOGRAPHIC_BENEFICIARIES => [
                ReportDimension::PROVINCE,
                ReportDimension::DISTRICT,
                ReportDimension::MUNICIPALITY,
                ReportDimension::BARANGAY,
            ],
            self::BENEFICIARY_SECTORS => [
                ReportDimension::SECTOR,
                ...$safeClassificationDimensions,
            ],
            self::INTERVENTION_FOCUS => [
                ReportDimension::INTERVENTION_FOCUS,
                ...array_values(array_filter(
                    $safeClassificationDimensions,
                    static fn (ReportDimension $dimension): bool =>
                        $dimension !== ReportDimension::INTERVENTION_FOCUS,
                )),
            ],
            self::LABOR_MARKET_REFERRALS => [
                ReportDimension::LABOR_MARKET_PROGRAM,
                ...$safeClassificationDimensions,
            ],
        };
    }

    public function allows(ReportDimension $dimension): bool
    {
        return in_array($dimension, $this->allowedDimensions(), true);
    }
}
