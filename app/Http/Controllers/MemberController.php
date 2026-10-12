<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Models\Member;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;

class MemberController extends Controller
{
    public function store(Plan $plan, MemberRequest $request): RedirectResponse
    {
        $plan->members()->create($request->validated());

        return $this->back($plan, 'Anggota berhasil ditambahkan.');
    }

    public function update(MemberRequest $request, Member $member): RedirectResponse
    {
        $member->update($request->validated());

        return $this->back($member->plan, 'Anggota berhasil diperbarui.');
    }

    /** Menghapus anggota otomatis melepasnya dari seluruh task. */
    public function destroy(Member $member): RedirectResponse
    {
        $plan = $member->plan;
        $member->tasks()->detach();
        $member->delete();

        return $this->back($plan, 'Anggota berhasil dihapus dan dilepas dari semua task.');
    }

    private function back(Plan $plan, string $message): RedirectResponse
    {
        return redirect()
            ->route('plans.show', ['plan' => $plan, 'tab' => 'anggota'])
            ->with('success', $message);
    }
}
