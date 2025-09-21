<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreGroupRequest
 * 
 * Form request for validating group creation data.
 */
class StoreGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization will be handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'type' => [
                'required',
                'string',
                Rule::in(['hospital', 'clinician_group']),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:groups,id',
                function ($attribute, $value, $fail) {
                    if ($value && $value !== '') {
                        $parent = Group::find($value);
                        if ($parent && !$parent->is_active) {
                            $fail('The selected parent group is not active.');
                        }
                    }
                },
            ],
            'is_active' => [
                'boolean',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The group name is required.',
            'name.string' => 'The group name must be a string.',
            'name.max' => 'The group name may not be greater than 255 characters.',
            'name.min' => 'The group name must be at least 2 characters.',
            'description.string' => 'The description must be a string.',
            'description.max' => 'The description may not be greater than 1000 characters.',
            'type.required' => 'The group type is required.',
            'type.in' => 'The group type must be either "hospital" or "clinician_group".',
            'parent_id.integer' => 'The parent ID must be an integer.',
            'parent_id.exists' => 'The selected parent group does not exist.',
            'is_active.boolean' => 'The active status must be true or false.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'group name',
            'description' => 'description',
            'type' => 'group type',
            'parent_id' => 'parent group',
            'is_active' => 'active status',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);

        // Convert empty string to null for parent_id
        if ($this->has('parent_id') && $this->input('parent_id') === '') {
            $this->merge(['parent_id' => null]);
        }
    }
}
