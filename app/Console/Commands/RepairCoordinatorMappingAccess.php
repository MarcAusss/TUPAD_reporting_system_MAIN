<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\CoordinatorProvinceAssignmentService;
use Illuminate\Console\Command;

final class RepairCoordinatorMappingAccess extends Command
{
    protected $signature = 'tupad:repair-tc-mapping-access
                            {--username= : Repair only one coordinator username}
                            {--province= : Explicit Bicol province PSGC code; requires --username}';

    protected $description = 'Repair TUPAD Coordinator province assignments required by Geographic Mapping.';

    public function handle(CoordinatorProvinceAssignmentService $assignments): int
    {
        $username = trim((string) $this->option('username'));
        $provinceCode = trim((string) $this->option('province'));

        if ($provinceCode !== '' && $username === '') {
            $this->error('--province requires --username so a province is never assigned to multiple coordinators accidentally.');

            return self::FAILURE;
        }

        $query = User::query()
            ->where('role', UserRole::TC->value)
            ->orderBy('username');

        if ($username !== '') {
            $query->where('username', $username);
        }

        $coordinators = $query->get();

        if ($coordinators->isEmpty()) {
            $this->error($username === ''
                ? 'No TUPAD Coordinator accounts were found.'
                : "No TUPAD Coordinator account with username {$username} was found.");

            return self::FAILURE;
        }

        $explicitProvince = null;

        if ($provinceCode !== '') {
            $explicitProvince = $assignments->validProvinceByCode($provinceCode);

            if ($explicitProvince === null) {
                $this->error("{$provinceCode} is not an active configured Bicol province PSGC code.");

                return self::FAILURE;
            }
        }

        $rows = [];
        $unresolved = 0;

        foreach ($coordinators as $coordinator) {
            $before = $assignments->validProvinceById($coordinator->assigned_province_id);

            if ($explicitProvince !== null) {
                $coordinator->forceFill([
                    'assigned_province_id' => $explicitProvince->id,
                ])->save();

                $province = $explicitProvince;
                $result = 'assigned explicitly';
            } else {
                $province = $assignments->resolve($coordinator, repair: true);
                $result = $province === null
                    ? 'UNRESOLVED'
                    : ($before?->id === $province->id ? 'already valid' : 'repaired');
            }

            if ($province === null) {
                $unresolved++;
            }

            $rows[] = [
                $coordinator->username,
                $coordinator->name,
                $province?->name ?? '—',
                $province?->code ?? '—',
                $result,
            ];
        }

        $this->table(
            ['Username', 'Coordinator', 'Province', 'PSGC', 'Result'],
            $rows,
        );

        if ($unresolved > 0) {
            $this->warn(
                "{$unresolved} coordinator assignment(s) could not be inferred safely. "
                .'Run this command again with --username=<name> --province=<9-digit PSGC> to assign them explicitly.'
            );

            return self::FAILURE;
        }

        $this->info('TUPAD Coordinator Geographic Mapping access assignments are valid.');

        return self::SUCCESS;
    }
}
