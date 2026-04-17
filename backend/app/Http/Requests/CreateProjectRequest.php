<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\ProjectInput;
use Illuminate\Foundation\Http\FormRequest;

class CreateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'street'  => ['required', 'string', 'min:2', 'max:200'],
            'city'    => ['required', 'string', 'min:2', 'max:120'],
            'country' => ['required', 'string', 'min:2', 'max:80'],
        ];
    }

    public function toDto(): ProjectInput
    {
        return new ProjectInput(
            street: trim((string) $this->string('street')),
            city: trim((string) $this->string('city')),
            country: trim((string) $this->string('country')),
        );
    }
}
