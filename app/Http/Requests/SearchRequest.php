<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Http\Exceptions\HttpResponseException;

use Illuminate\Contracts\Validation\Validator;


class SearchRequest extends FormRequest

{

    public function rules()
    {
        return [
            'search_string' => 'required|max:255',
            'catid' => 'required|integer',
        ];

    }


    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ]));
    }


    public function messages()
    {
        return [
            'search_string.required' => 'Search string is required'
        ];

    }

}
