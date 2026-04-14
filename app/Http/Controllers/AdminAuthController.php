<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;
use Illuminate\Http\Request;
use App\Models\AdminAction;

class AdminAuthController extends Controller
{
    public function login(Request $request)
{
    $admin = Admin::where('username', $request->username)->first();

    if (!$admin || !Hash::check($request->password, $admin->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    return response()->json([
        'message' => 'Login success',
        'admin' => $admin
    ]);
}
public function index()
{
    $reports = Report::with(['mlResult', 'adminActions'])
        ->latest()
        ->get();

    return response()->json($reports);
}
public function updateStatus(Request $request, $reportId)
{
    $request->validate([
        'action' => 'required|in:triage,verifikasi,mitigasi,close',
        'priority' => 'nullable|in:low,medium,high',
        'sla' => 'nullable|integer'
    ]);

    $action = AdminAction::create([
        'report_id' => $reportId,
        'action' => $request->action,
        'priority' => $request->priority,
        'sla' => $request->sla,
    ]);

    return response()->json([
        'message' => 'Action updated',
        'data' => $action
    ]);
}
}
