<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Models\Plan;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks)
    {
    }

    public function store(Plan $plan, TaskRequest $request): RedirectResponse
    {
        $result = $this->tasks->save($plan, null, $request->validated());

        return $this->back($plan, 'Task berhasil ditambahkan.', $result['warnings']);
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $result = $this->tasks->save($task->plan, $task, $request->validated());

        return $this->back($task->plan, 'Task berhasil diperbarui.', $result['warnings']);
    }

    /** Hapus task: sub-task dan dependency terkait ikut terhapus. */
    public function destroy(Task $task): RedirectResponse
    {
        $plan = $task->plan;
        $this->tasks->delete($task);

        return $this->back($plan, 'Task beserta sub-task dan dependency terkait berhasil dihapus.');
    }

    /** Tombol cepat: ubah progress task (status tetap dihitung otomatis).
     *  Pada task induk, progress seluruh sub-tasknya ikut diubah. */
    public function progress(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate(
            ['progress' => ['required', 'integer', 'min:0', 'max:100']],
            [
                'progress.required' => 'Progress wajib diisi.',
                'progress.integer' => 'Progress harus bilangan bulat.',
                'progress.min' => 'Progress harus antara 0 dan 100.',
                'progress.max' => 'Progress harus antara 0 dan 100.',
            ]
        );

        $value = (int) $data['progress'];
        $hasChildren = $task->children()->exists();

        if ($hasChildren) {
            // Task induk: progress berasal dari sub-task, jadi ubah semua sub-task.
            $this->setProgressRecursive($task, $value);
        } else {
            $task->progress = $value;
            $task->save();
        }

        if ($hasChildren) {
            $message = $value >= 100
                ? 'Task "'.$task->name.'" beserta seluruh sub-task ditandai selesai.'
                : 'Progress seluruh sub-task "'.$task->name.'" dibuka kembali menjadi '.$value.'%.';
        } else {
            $message = $value >= 100
                ? 'Task "'.$task->name.'" ditandai selesai.'
                : 'Progress task "'.$task->name.'" diubah menjadi '.$value.'%.';
        }

        return $this->back($task->plan, $message);
    }

    /** Ubah progress satu task dan seluruh sub-tasknya (maksimal satu tingkat). */
    private function setProgressRecursive(Task $task, int $value): void
    {
        foreach ($task->children as $child) {
            $child->progress = $value;
            $child->save();
        }
    }

    /**
     * @param list<string> $warnings
     */
    private function back(Plan $plan, string $message, array $warnings = []): RedirectResponse
    {
        $response = redirect()
            ->route('plans.show', ['plan' => $plan, 'tab' => 'tugas'])
            ->with('success', $message);

        if ($warnings !== []) {
            $response = $response->with('warnings', $warnings);
        }

        return $response;
    }
}
