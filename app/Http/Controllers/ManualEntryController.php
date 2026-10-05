<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ManualEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Facebook個人・TikTok・X など、APIで取らないアカウントの閲覧数を入力する */
class ManualEntryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canSeeDetails(), 403);

        $accounts = Account::query()
            ->with('brand')
            ->where('input_method', Account::METHOD_MANUAL)
            ->whereIn('brand_id', $request->user()->visibleBrands()->pluck('id'))
            ->get()
            ->sortBy(fn (Account $a) => [$a->brand->sort_order, $a->brand_id, $a->platform->value]);

        return view('manual.index', ['accounts' => $accounts]);
    }

    public function show(Request $request, Account $account): View
    {
        abort_unless($request->user()->canManageAccount($account), 403);

        return view('manual.show', [
            'account' => $account->load('brand'),
            'entries' => $account->manualEntries()->with('creator')->orderByDesc('period_start')->paginate(30),
        ]);
    }

    public function store(Request $request, Account $account): RedirectResponse
    {
        abort_unless($request->user()->canManageAccount($account), 403);

        $data = $request->validate([
            'format' => ['required', Rule::in(array_keys($account->platform->formats()))],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'views' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $account->manualEntries()->create($data + ['created_by' => $request->user()->id]);

        return back()->with('status', '入力しました。');
    }

    public function destroy(Request $request, ManualEntry $entry): RedirectResponse
    {
        abort_unless($request->user()->canManageAccount($entry->account), 403);

        $entry->delete();

        return back()->with('status', '削除しました。');
    }
}
