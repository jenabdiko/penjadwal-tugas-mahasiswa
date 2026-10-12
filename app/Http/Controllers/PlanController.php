<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanRequest;
use App\Models\Plan;
use App\Services\GanttService;
use App\Services\PertService;
use App\Services\PlanJsonService;
use App\Services\SchedulerService;
use App\Services\StatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlanController extends Controller
{
    private const TABS = ['ringkasan', 'tugas', 'anggota', 'gantt', 'jaringan', 'kritis'];

    public function __construct(
        private readonly SchedulerService $scheduler,
        private readonly PlanJsonService $json,
        private readonly GanttService $gantt,
        private readonly PertService $pert,
        private readonly StatusService $status,
    ) {
    }

    /** Beranda: daftar rencana beserta status otomatis, progress, dan evaluasi deadline. */
    public function index(): View
    {
        $plans = Plan::all();

        $evaluations = [];
        $statuses = [];
        $progresses = [];
        foreach ($plans as $plan) {
            $schedule = $this->scheduler->schedule($plan);
            $evaluations[$plan->id] = $schedule['project']['deadline'];
            $statuses[$plan->id] = $this->status->compute($plan, $schedule)['plan'];
            $progresses[$plan->id] = $this->progressOf($schedule);
        }

        // Urutkan: deadline terdekat lebih dulu, rencana Selesai paling akhir.
        $plans = $plans->sortBy([
            fn (Plan $plan): int => $statuses[$plan->id] === Plan::STATUS_DONE ? 1 : 0,
            fn (Plan $plan): string => $plan->deadline->toDateString(),
        ])->values();

        // Rencana berisiko terlambat: status Terlambat ATAU evaluasi melewati deadline.
        $atRisk = 0;
        $progressSum = 0;
        foreach ($plans as $plan) {
            if ($statuses[$plan->id] === Plan::STATUS_LATE || ! $evaluations[$plan->id]['on_time']) {
                $atRisk++;
            }
            $progressSum += $progresses[$plan->id];
        }
        $avgProgress = $plans->isNotEmpty() ? (int) round($progressSum / $plans->count()) : 0;

        return view('plans.index', compact('plans', 'evaluations', 'statuses', 'progresses', 'atRisk', 'avgProgress'));
    }

    public function create(): View
    {
        return view('plans.form', ['plan' => null]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        $plan = $this->persist($request->validated());

        return redirect()
            ->route('plans.show', ['plan' => $plan, 'tab' => 'ringkasan'])
            ->with('success', 'Rencana berhasil dibuat.');
    }

    /** Halaman rencana dengan tab via query string ?tab=. */
    public function show(Request $request, Plan $plan): View
    {
        $tab = (string) $request->query('tab', 'ringkasan');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'ringkasan';
        }

        $plan->load([
            'members' => fn ($query) => $query->orderBy('name'),
            'holidays' => fn ($query) => $query->orderBy('date'),
        ]);

        $schedule = $this->scheduler->schedule($plan);
        $gantt = $tab === 'gantt' ? $this->gantt->build($plan, $schedule) : null;
        $pert = $tab === 'jaringan' ? $this->pert->build($plan, $schedule) : null;

        // Konteks form task: tambah (dengan induk opsional) atau ubah.
        $formMode = null;
        $editTask = null;
        $addParentId = null;
        $action = $request->query('action');
        if ($tab === 'tugas' && in_array($action, ['add', 'edit'], true)) {
            $formMode = $action;
            if ($action === 'edit') {
                $editTask = $plan->tasks()->find($request->query('task'));
            } else {
                $parent = $plan->tasks()->find($request->query('parent'));
                // Hanya task utama dalam rencana ini yang boleh jadi induk.
                $addParentId = $parent !== null && $parent->parent_id === null ? $parent->id : null;
            }
        }

        // Konteks form anggota.
        $editMember = null;
        if ($tab === 'anggota' && $action === 'edit') {
            $editMember = $plan->members()->find($request->query('member'));
        }

        $summary = null;
        if ($tab === 'ringkasan') {
            $summary = $this->buildSummary($plan, $schedule);
        }

        $planStatuses = $this->status->compute($plan, $schedule);

        return view('plans.show', [
            'plan' => $plan,
            'tab' => $tab,
            'tabs' => self::TABS,
            'schedule' => $schedule,
            'gantt' => $gantt,
            'pert' => $pert,
            'summary' => $summary,
            'planStatus' => $planStatuses['plan'],
            'taskStatuses' => $planStatuses['tasks'],
            'formMode' => $formMode,
            'editTask' => $editTask,
            'addParentId' => $addParentId,
            'editMember' => $editMember,
            'taskOptions' => $plan->tasks()->orderBy('sort_order')->get(['id', 'name', 'parent_id']),
        ]);
    }

    public function edit(Plan $plan): View
    {
        return view('plans.form', ['plan' => $plan]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->persist($request->validated(), $plan);

        return redirect()
            ->route('plans.show', ['plan' => $plan, 'tab' => 'ringkasan'])
            ->with('success', 'Rencana berhasil diperbarui.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        DB::transaction(function () use ($plan): void {
            foreach ($plan->tasks as $task) {
                $task->dependencies()->delete();
                $task->predecessorDependencies()->delete();
                $task->members()->detach();
            }
            $plan->delete(); // seluruh data terkait ikut terhapus (cascade)
        });

        return redirect()->route('plans.index')->with('success', 'Rencana berhasil dihapus.');
    }

    /** Ekspor satu rencana lengkap sebagai berkas .json. */
    public function export(Plan $plan): JsonResponse
    {
        $filename = 'rencana-'.(Str::slug($plan->name) ?: $plan->id).'.json';

        return response()->json(
            $this->json->export($plan),
            200,
            ['Content-Disposition' => 'attachment; filename="'.$filename.'"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
    }

    /** Simpan rencana (baru/perubahan) beserta daftar hari liburnya. */
    private function persist(array $data, ?Plan $plan = null): Plan
    {
        return DB::transaction(function () use ($data, $plan): Plan {
            $attributes = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'],
                'deadline' => $data['deadline'],
                'priority' => $data['priority'],
                'off_saturday' => (bool) ($data['off_saturday'] ?? false),
                'off_sunday' => (bool) ($data['off_sunday'] ?? false),
            ];

            $plan = $plan ?? new Plan;
            $plan->fill($attributes);
            $plan->save();

            $plan->holidays()->delete();
            foreach ($data['holidays'] ?? [] as $holiday) {
                if (!is_array($holiday) || empty($holiday['date'])) {
                    continue;
                }
                $plan->holidays()->create([
                    'date' => $holiday['date'],
                    'name' => $holiday['name'],
                ]);
            }

            return $plan;
        });
    }

    /** Ringkasan tab Beranda rencana: progress, task selesai, dan tugas mendesak. */
    private function buildSummary(Plan $plan, array $schedule): array
    {
        $completed = 0;
        foreach ($schedule['tasks'] as $info) {
            if ($info['progress'] >= 100) {
                $completed++;
            }
        }

        $today = now()->toDateString();
        $limit = date('Y-m-d', strtotime($today.' +3 days'));
        $dueSoon = [];
        foreach ($schedule['rows'] as $row) {
            if ($row['is_parent'] || $row['progress'] >= 100) {
                continue;
            }
            if ($row['end_date'] !== null && $row['end_date'] >= $today && $row['end_date'] <= $limit) {
                $dueSoon[] = $row;
            }
        }

        return [
            'progress' => $this->progressOf($schedule),
            'completed' => $completed,
            'total_tasks' => count($schedule['tasks']),
            'due_soon' => $dueSoon,
        ];
    }

    /** Progress rencana keseluruhan: rata-rata progress task berbobot durasi (persen). */
    private function progressOf(array $schedule): int
    {
        $weight = 0;
        $total = 0;
        foreach ($schedule['tasks'] as $info) {
            $weight += $info['duration'];
            $total += $info['duration'] * $info['progress'];
        }

        return $weight > 0 ? (int) round($total / $weight) : 0;
    }
}
