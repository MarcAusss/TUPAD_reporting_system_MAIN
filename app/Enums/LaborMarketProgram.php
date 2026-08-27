<?php

namespace App\Enums;

enum LaborMarketProgram: string
{
    case SKILLS_TRAINING = 'skills_training';
    case DOLE_INTEGRATED_LIVELIHOOD_PROGRAM = 'dole_integrated_livelihood_program';
    case EMPLOYMENT_FACILITATION_SERVICES = 'employment_facilitation_services';

    public function label(): string
    {
        return match ($this) {
            self::SKILLS_TRAINING => 'Skills Training',
            self::DOLE_INTEGRATED_LIVELIHOOD_PROGRAM => 'DOLE Integrated Livelihood Program (DILP)',
            self::EMPLOYMENT_FACILITATION_SERVICES => 'Employment Facilitation Services',
        };
    }
}
