<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use Illuminate\View\View;

class SyncLogController extends Controller
{
    public function index(): View
    {
        return view('admin.logs', ['logs' => SyncLog::with('account.brand')->orderByDesc('id')->paginate(50)]);
    }
}
