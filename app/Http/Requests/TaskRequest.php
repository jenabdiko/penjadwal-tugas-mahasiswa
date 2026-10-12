<?php

namespace App\Http\Requests;

use App\Models\Plan;
use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Buang baris predecessor yang belum memilih task sebelum validasi. */
    protected function prepareForValidation(): void
    {
        $predecessors = $this->input('predecessors');
        if (is_array($predecessors)) {
            $predecessors = array_filter(
                $predecessors,
                fn ($row): bool => is_array($row) && !empty($row['task_id'])
            );
            $this->merge(['predecessors' => array_values($predecessors)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'duration' => ['required', 'integer', 'min:1'],
            'earliest_start' => ['nullable', 'date'],
            'parent_id' => ['nullable', 'integer', $this->parentRule()],
            'priority' => ['required', Rule::in(Plan::PRIORITIES)],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', $this->memberRule()],
            'predecessors' => ['nullable', 'array'],
            'predecessors.*.task_id' => ['required', 'integer'],
            'predecessors.*.type' => ['required', Rule::in(TaskDependency::TYPES)],
            'predecessors.*.lag' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Aturan induk task: harus dalam rencana yang sama, bukan dirinya sendiri,
     * bukan sub-task, dan task yang sudah punya sub-task tidak boleh jadi sub-task.
     */
    private function parentRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $current = $this->route('task');
            $plan = $this->route('plan') ?? $current?->plan;
            $parentId = (int) $value;

            if ($current !== null && $current->id === $parentId) {
                $fail('Task tidak boleh menjadi induknya sendiri.');

                return;
            }

            $parent = Task::find($parentId);
            if ($parent === null || ($plan !== null && $parent->plan_id !== $plan->id)) {
                $fail('Induk task tidak ditemukan dalam rencana ini.');

                return;
            }

            if ($parent->parent_id !== null) {
                $fail('Sub-task tidak boleh dijadikan induk. Pilih task utama.');

                return;
            }

            if ($current !== null && $current->children()->exists()) {
                $fail('Task yang sudah punya sub-task tidak boleh menjadi sub-task lain.');
            }
        };
    }

    /** Anggota harus berasal dari rencana yang sama. */
    private function memberRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $current = $this->route('task');
            $plan = $this->route('plan') ?? $current?->plan;

            $member = \App\Models\Member::find($value);
            if ($member === null || ($plan !== null && $member->plan_id !== $plan->id)) {
                $fail('Anggota tidak ditemukan dalam rencana ini.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama task wajib diisi.',
            'name.max' => 'Nama task maksimal 255 karakter.',
            'duration.required' => 'Durasi wajib diisi.',
            'duration.integer' => 'Durasi harus bilangan bulat.',
            'duration.min' => 'Durasi harus bilangan bulat minimal 1.',
            'earliest_start.date' => 'Tanggal mulai paling awal harus tanggal yang valid.',
            'parent_id.integer' => 'Induk task tidak valid.',
            'priority.in' => 'Prioritas tidak valid.',
            'progress.required' => 'Progress wajib diisi.',
            'progress.integer' => 'Progress harus bilangan bulat.',
            'progress.min' => 'Progress harus antara 0 dan 100.',
            'progress.max' => 'Progress harus antara 0 dan 100.',
            'members.array' => 'Daftar penanggung jawab tidak valid.',
            'predecessors.array' => 'Daftar predecessor tidak valid.',
            'predecessors.*.task_id.required' => 'Task predecessor wajib dipilih.',
            'predecessors.*.task_id.integer' => 'Task predecessor tidak valid.',
            'predecessors.*.type.required' => 'Jenis dependency wajib dipilih.',
            'predecessors.*.type.in' => 'Jenis dependency harus FS, SS, FF, atau SF.',
            'predecessors.*.lag.required' => 'Lag wajib diisi.',
            'predecessors.*.lag.integer' => 'Lag harus bilangan bulat.',
            'predecessors.*.lag.min' => 'Lag harus bilangan bulat minimal 0.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama task',
            'duration' => 'durasi',
            'earliest_start' => 'tanggal mulai paling awal',
            'parent_id' => 'induk task',
            'priority' => 'prioritas',
            'progress' => 'progress',
            'predecessors.*.task_id' => 'task predecessor',
            'predecessors.*.type' => 'jenis dependency',
            'predecessors.*.lag' => 'lag',
        ];
    }
}
