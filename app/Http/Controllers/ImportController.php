<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportRequest;
use App\Services\PlanJsonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ImportController extends Controller
{
    public function create(): View
    {
        return view('import');
    }

    /** Impor berkas JSON menjadi rencana baru. */
    public function store(ImportRequest $request, PlanJsonService $json): RedirectResponse
    {
        $raw = file_get_contents((string) $request->file('file')->getRealPath());
        $payload = json_decode((string) $raw, true);

        if (!is_array($payload)) {
            throw ValidationException::withMessages([
                'file' => 'Berkas JSON tidak valid atau formatnya rusak. Pastikan berkas berasal dari tombol Ekspor JSON.',
            ]);
        }

        $plan = $json->import($payload);

        return redirect()
            ->route('plans.show', ['plan' => $plan, 'tab' => 'ringkasan'])
            ->with('success', 'Rencana "'.$plan->name.'" berhasil diimpor sebagai rencana baru.');
    }
}
