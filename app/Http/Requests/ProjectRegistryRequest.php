<?php

namespace App\Http\Requests;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRegistryRequest extends FormRequest
{
    private const SORTS = [
        'newest',
        'oldest',
        'title_asc',
        'title_desc',
        'beneficiaries_desc',
        'beneficiaries_asc',
        'cost_desc',
        'cost_asc',
        'updated_desc',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'implementation_mode' => ['nullable', Rule::enum(ImplementationMode::class)],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'fiscal_year' => ['nullable', 'integer', 'between:2000,2100'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
        ];
    }
}

