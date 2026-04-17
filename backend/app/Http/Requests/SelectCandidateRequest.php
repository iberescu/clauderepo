<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelectCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'index' => ['required', 'integer', 'min:1'],
        ];
    }

    public function index(): int
    {
        return (int) $this->integer('index');
    }
}
