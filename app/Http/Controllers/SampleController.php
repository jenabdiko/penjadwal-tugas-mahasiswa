<?php

namespace App\Http\Controllers;

use App\Services\SampleDataService;
use Illuminate\Http\RedirectResponse;

class SampleController extends Controller
{
    public function __construct(private readonly SampleDataService $samples)
    {
    }

    /** Muat dua rencana contoh; tidak membuat ganda bila sudah ada. */
    public function load(): RedirectResponse
    {
        $result = $this->samples->load();

        if ($result['created'] === []) {
            return redirect()
                ->route('plans.index')
                ->with('warning', 'Data contoh sudah pernah dimuat, tidak dibuat ganda.');
        }

        return redirect()
            ->route('plans.index')
            ->with('success', 'Data contoh berhasil dimuat: '.implode(', ', $result['created']).'.');
    }
}
