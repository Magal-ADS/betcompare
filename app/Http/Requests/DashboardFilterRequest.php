<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'region' => ['nullable', 'string', Rule::in(['america', 'asia', 'europe'])],
            'country' => ['nullable', 'string', 'max:100'],
            'competition' => ['nullable', 'string', 'max:150'],
            'game' => ['nullable', 'integer', Rule::exists('events', 'id')],
            'sort' => ['nullable', 'string', Rule::in(['time', 'difference_desc', 'difference_asc', 'team'])],
        ];
    }
}
