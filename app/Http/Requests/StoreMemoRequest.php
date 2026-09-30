<?php

namespace App\Http\Requests;

use App\Models\Stable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMemoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('memos.max_kilobytes'),
                'extensions:m4a,mp3,wav,aac,mp4,webm,caf,ogg',
            ],
            'tenant' => ['required', 'string', 'size:8', 'regex:/^[A-Z0-9]+$/'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'An audio file is required.',
            'file.extensions' => 'Audio must be m4a, mp3, wav, aac, mp4, webm, caf, or ogg.',
            'tenant.required' => 'A tenant code is required via the X-Tenant header or tenant form field.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $header = $this->header('X-Tenant');
        $tenant = is_string($header) && $header !== ''
            ? $header
            : $this->input('tenant');

        $this->merge([
            'tenant' => is_string($tenant) ? strtoupper(trim($tenant)) : $tenant,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $stable = Stable::query()
                ->where('tenant_code', $this->input('tenant'))
                ->first();

            if ($stable === null) {
                $validator->errors()->add('tenant', 'Unknown tenant code.');

                return;
            }

            if (! $stable->is_active) {
                $validator->errors()->add('tenant', 'This stable is not accepting memos.');

                return;
            }

            $this->attributes->set('stable', $stable);
        });
    }

    public function stable(): Stable
    {
        $stable = $this->attributes->get('stable');

        if (! $stable instanceof Stable) {
            $stable = Stable::query()
                ->where('tenant_code', $this->validated('tenant'))
                ->where('is_active', true)
                ->firstOrFail();
        }

        return $stable;
    }
}
