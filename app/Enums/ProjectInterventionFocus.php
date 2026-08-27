<?php

namespace App\Enums;

enum ProjectInterventionFocus: string
{
    case DISASTER_RISK_REDUCTION_AND_MITIGATION = 'disaster_risk_reduction_and_mitigation';
    case EMERGENCY_PREPAREDNESS = 'emergency_preparedness';
    case ENVIRONMENTAL_CONSERVATION = 'environmental_conservation';
    case EARLY_RECOVERY_AND_REHABILITATION = 'early_recovery_and_rehabilitation';
    case ADMINISTRATIVE_CLERICAL_AND_LOGISTICAL_SUPPORT = 'administrative_clerical_and_logistical_support';

    public function label(): string
    {
        return match ($this) {
            self::DISASTER_RISK_REDUCTION_AND_MITIGATION => 'Disaster Risk Reduction and Mitigation',
            self::EMERGENCY_PREPAREDNESS => 'Emergency Preparedness',
            self::ENVIRONMENTAL_CONSERVATION => 'Environmental Conservation',
            self::EARLY_RECOVERY_AND_REHABILITATION => 'Early Recovery and Rehabilitation',
            self::ADMINISTRATIVE_CLERICAL_AND_LOGISTICAL_SUPPORT => 'Administrative, Clerical and Logistical Support',
        };
    }
}
