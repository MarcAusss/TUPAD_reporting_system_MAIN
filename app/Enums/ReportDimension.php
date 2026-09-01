<?php

namespace App\Enums;

enum ReportDimension: string
{
    case OVERALL = 'overall';
    case MONTH = 'month';
    case QUARTER = 'quarter';
    case SEMESTER = 'semester';
    case FISCAL_YEAR = 'fiscal_year';
    case TERM = 'term';
    case ADL = 'adl';
    case PROVINCE = 'province';
    case DISTRICT = 'district';
    case MUNICIPALITY = 'municipality';
    case BARANGAY = 'barangay';
    case STATUS = 'status';
    case SPONSOR = 'sponsor';
    case PARTNER = 'partner';
    case LCE = 'lce';
    case PROJECT_CODE = 'project_code';
    case SECTOR = 'sector';
    case INTERVENTION_FOCUS = 'intervention_focus';
    case LABOR_MARKET_PROGRAM = 'labor_market_program';

    public function label(): string
    {
        return match ($this) {
            self::OVERALL => 'Overall',
            self::MONTH => 'Month',
            self::QUARTER => 'Quarter',
            self::SEMESTER => 'Semester',
            self::FISCAL_YEAR => 'Fiscal Year',
            self::TERM => 'Term',
            self::ADL => 'ADL',
            self::PROVINCE => 'Province',
            self::DISTRICT => 'District',
            self::MUNICIPALITY => 'Municipality',
            self::BARANGAY => 'Barangay',
            self::STATUS => 'Status',
            self::SPONSOR => 'Sponsor',
            self::PARTNER => 'Partner / NGA',
            self::LCE => 'LCE / Party-list',
            self::PROJECT_CODE => 'Project Code',
            self::SECTOR => 'Beneficiary Sector',
            self::INTERVENTION_FOCUS => 'Intervention Focus',
            self::LABOR_MARKET_PROGRAM => 'Labor Market Program',
        };
    }

    public function isFineGeography(): bool
    {
        return in_array($this, [
            self::DISTRICT,
            self::MUNICIPALITY,
            self::BARANGAY,
        ], true);
    }

    public function isPeriod(): bool
    {
        return in_array($this, [
            self::MONTH,
            self::QUARTER,
            self::SEMESTER,
            self::FISCAL_YEAR,
        ], true);
    }
}
