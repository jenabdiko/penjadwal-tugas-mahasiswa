<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemberRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama anggota wajib diisi.',
            'name.max' => 'Nama anggota maksimal 255 karakter.',
            'role.required' => 'Peran anggota wajib diisi.',
            'role.max' => 'Peran anggota maksimal 255 karakter.',
            'contact.max' => 'Kontak maksimal 255 karakter.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama anggota',
            'role' => 'peran anggota',
            'contact' => 'kontak',
        ];
    }
}
