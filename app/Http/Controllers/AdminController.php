<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(Request $request): View
    {
        $logs = DB::table('uploads_log')->orderByDesc('uploaded_at')->limit(10)->get();
        $msg = $request->session()->get('error') ?? $request->session()->get('success');

        return view('admin.dashboard', compact('logs', 'msg'));
    }
}
