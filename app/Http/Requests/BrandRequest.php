<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    /**
     * Indicates if the validator should stop on the first rule failure.
     *
     * @var bool
     */
    protected $stopOnFirstFailure = true;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rulesArray = [
            'description'                     => ['nullable','string', 'max:250'],
            'status'                          => ['required','numeric'],
        ];

        if ($this->isMethod('PUT')) {
            $brandId             = $this->input('id');
            $rulesArray['id']           = ['required'];
            $rulesArray['name']          = ['required', 'string', 'max:100', Rule::unique('brands')->where('name', $_POST['name'])->ignore($brandId)];
        }else{
            $rulesArray['name']          = ['required', 'string', 'max:100', Rule::unique('brands')->where('name', $_POST['name'])];
        }

        return $rulesArray;
    }
}
