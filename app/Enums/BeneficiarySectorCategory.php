<?php

namespace App\Enums;

enum BeneficiarySectorCategory: string
{
    public const GROUP_PRIORITY_VULNERABLE = 'priority_vulnerable';

    public const GROUP_OCCUPATIONAL_LIVELIHOOD = 'occupational_livelihood';

    case FEMALES = 'females';
    case YOUTH = 'youth';
    case SENIOR_CITIZENS = 'senior_citizens';
    case PERSONS_WITH_DISABILITIES = 'persons_with_disabilities';
    case SOLO_PARENTS = 'solo_parents';
    case INDIGENOUS_PEOPLES = 'indigenous_peoples';
    case FORMER_REBELS = 'former_rebels';
    case PERSONS_DEPRIVED_OF_LIBERTY = 'persons_deprived_of_liberty';
    case PAROLEES_AND_PROBATIONERS = 'parolees_and_probationers';

    case TRANSPORT_WORKERS = 'transport_workers';
    case VENDORS = 'vendors';
    case CROP_GROWERS = 'crop_growers';
    case HOMEBASED_WORKERS = 'homebased_workers';
    case FISHERS_FISHERFOLK = 'fishers_fisherfolk';
    case LIVESTOCK_POULTRY_RAISERS = 'livestock_poultry_raisers';
    case SMALL_TRANSPORT_DRIVERS = 'small_transport_drivers';
    case LABORERS = 'laborers';
    case HOUSE_HELPERS = 'house_helpers';
    case OTHERS = 'others';

    public function label(): string
    {
        return match ($this) {
            self::FEMALES => 'Females',
            self::YOUTH => 'Youth',
            self::SENIOR_CITIZENS => 'Senior Citizens',
            self::PERSONS_WITH_DISABILITIES => 'Persons with Disabilities (PWDs)',
            self::SOLO_PARENTS => 'Solo Parents',
            self::INDIGENOUS_PEOPLES => 'Indigenous Peoples (IPs)',
            self::FORMER_REBELS => 'Former Rebels (FRs)',
            self::PERSONS_DEPRIVED_OF_LIBERTY => 'Persons Deprived of Liberty (PDLs)',
            self::PAROLEES_AND_PROBATIONERS => 'Parolees and Probationers',
            self::TRANSPORT_WORKERS => 'Transport Workers',
            self::VENDORS => 'Vendors',
            self::CROP_GROWERS => 'Crop Growers',
            self::HOMEBASED_WORKERS => 'Homebased Workers',
            self::FISHERS_FISHERFOLK => 'Fishers / Fisherfolk',
            self::LIVESTOCK_POULTRY_RAISERS => 'Livestock / Poultry Raisers',
            self::SMALL_TRANSPORT_DRIVERS => 'Small Transport Drivers',
            self::LABORERS => 'Laborers',
            self::HOUSE_HELPERS => 'House Helpers',
            self::OTHERS => 'Others',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::FEMALES,
            self::YOUTH,
            self::SENIOR_CITIZENS,
            self::PERSONS_WITH_DISABILITIES,
            self::SOLO_PARENTS,
            self::INDIGENOUS_PEOPLES,
            self::FORMER_REBELS,
            self::PERSONS_DEPRIVED_OF_LIBERTY,
            self::PAROLEES_AND_PROBATIONERS => self::GROUP_PRIORITY_VULNERABLE,

            self::TRANSPORT_WORKERS,
            self::VENDORS,
            self::CROP_GROWERS,
            self::HOMEBASED_WORKERS,
            self::FISHERS_FISHERFOLK,
            self::LIVESTOCK_POULTRY_RAISERS,
            self::SMALL_TRANSPORT_DRIVERS,
            self::LABORERS,
            self::HOUSE_HELPERS,
            self::OTHERS => self::GROUP_OCCUPATIONAL_LIVELIHOOD,
        };
    }

    public function groupLabel(): string
    {
        return match ($this->group()) {
            self::GROUP_PRIORITY_VULNERABLE => 'Priority / Vulnerable Sectors',
            self::GROUP_OCCUPATIONAL_LIVELIHOOD => 'Occupational / Livelihood Sectors',
        };
    }

    /** @return array<int, self> */
    public static function priorityVulnerable(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $category): bool =>
                $category->group() === self::GROUP_PRIORITY_VULNERABLE,
        ));
    }

    /** @return array<int, self> */
    public static function occupationalLivelihood(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $category): bool =>
                $category->group() === self::GROUP_OCCUPATIONAL_LIVELIHOOD,
        ));
    }
}
