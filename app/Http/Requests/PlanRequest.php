<?php

namespace App\Http\Requests;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Buang baris hari libur yang benar-benar kosong sebelum validasi. */
    protected function prepareForValidation(): void
    {
        $holidays = $this->input('holidays');
        if (is_array($holidays)) {
            $holidays = array_filter($holidays, function ($row): bool {
                if (!is_array($row)) {
                    return false;
                }

                return !empty($row['date']) || trim((string) ($row['name'] ?? '')) !== '';
            });
            $this->merge(['holidays' => array_values($holidays)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'deadline' => ['required', 'date', 'after_or_equal:start_date'],
            'priority' => ['required', Rule::in(Plan::PRIORITIES)],
            'off_saturday' => ['nullable', 'boolean'],
            'off_sunday' => ['nullable', 'boolean'],
            'holidays' => ['nullable', 'array'],
            'holidays.*.date' => ['required', 'date'],
            'holidays.*.name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama rencana wajib diisi.',
            'name.max' => 'Nama rencana maksimal 255 karakter.',
            'description.max' => 'Deskripsi maksimal 2000 karakter.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date' => 'Tanggal mulai harus tanggal yang valid.',
            'deadline.required' => 'Deadline wajib diisi.',
            'deadline.date' => 'Deadline harus tanggal yang valid.',
            'deadline.after_or_equal' => 'Deadline tidak boleh sebelum tanggal mulai.',
            'priority.in' => 'Prioritas tidak valid.',
            'off_saturday.boolean' => 'Checkbox Sabtu libur tidak valid.',
            'off_sunday.boolean' => 'Checkbox Minggu libur tidak valid.',
            'holidays.array' => 'Daftar hari libur tidak valid.',
            'holidays.*.date.required' => 'Tanggal hari libur wajib diisi.',
            'holidays.*.date' => 'Tanggal hari libur harus tanggal yang valid.',
            'holidays.*.name.required' => 'Nama hari libur wajib diisi.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama rencana',
            'description' => 'deskripsi',
            'start_date' => 'tanggal mulai',
            'deadline' => 'deadline',
            'priority' => 'prioritas',
            'holidays.*.date' => 'tanggal hari libur',
            'holidays.*.name' => 'nama hari libur',
        ];
    }
}
