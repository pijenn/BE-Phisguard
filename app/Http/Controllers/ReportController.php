<?php

namespace App\Http\Controllers;
use App\Models\Report;
use App\Models\MlResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
   public function store(Request $request)
{
    $request->validate([
        'channel_chat' => 'required|string',
        'sender_account' => 'required|string',
        'chat_text' => 'required|string',
    ]);

    // 1. Create report
    $report = Report::create([
        'ticket' => Report::generateTicket(),
        'channel_chat' => $request->channel_chat,
        'sender_account' => $request->sender_account,
        'chat_text' => $request->chat_text,
        'url' => $request->url,
        'reporter_name' => $request->reporter_name,
        'region' => $request->region,
        'modus_type' => $request->modus_type,
        'evidence_text' => $request->evidence_text,
        'user_segment' => $request->user_segment,
        'incident_summary' => $request->incident_summary,
    ]);

    // 2. Call ML (dummy dulu)
    $mlResult = MlResult::create([
        'report_id' => $report->id,
        'label' => 'phishing',
        'risk_score' => rand(60, 100),
        'priority' => 'high',
        'reason' => 'Terdeteksi mengandung URL mencurigakan'
    ]);

    return response()->json([
        'message' => 'Report submitted successfully',
        'ticket' => $report->ticket,
        'ml_result' => $mlResult
    ]);
}

public function show($id)
{
    $report = Report::with([
        'mlResult',
        'adminActions'
    ])->find($id);

    if (!$report) {
        return response()->json([
            'message' => 'Report not found'
        ], 404);
    }

    return response()->json($report);
}
public function index()
{
    $reports = Report::with([
        'mlResult' => function ($query) {
            $query->select('*');
        },
        'adminActions' => function ($query) {
            $query->latest();
        }
    ])->get();

    return response()->json($reports);
}
public function weeklyTrend()
{
    $data = DB::table('reports')
        ->join('ml_results', 'reports.id', '=', 'ml_results.report_id')
        ->where('ml_results.label', 'phishing')
        ->whereNotNull('reports.created_at')
        ->selectRaw("DATE_FORMAT(reports.created_at, '%Y-%u') as week, COUNT(*) as total")
        ->groupBy('week')
        ->orderBy('week', 'asc')
        ->get();

    return response()->json($data);
}

public function topChannel()
{
    $data = DB::table('reports')
        ->join('ml_results', 'reports.id', '=', 'ml_results.report_id')
        ->where('ml_results.label', 'phishing')
        ->select('channel_chat', DB::raw('COUNT(*) as total'))
        ->groupBy('channel_chat')
        ->orderByDesc('total')
        ->limit(5)
        ->get();

    return response()->json($data);
}
public function topModus()
{
    $data = DB::table('reports')
        ->join('ml_results', 'reports.id', '=', 'ml_results.report_id')
        ->where('ml_results.label', 'phishing')
        ->select('modus_type', DB::raw('COUNT(*) as total'))
        ->groupBy('modus_type')
        ->orderByDesc('total')
        ->limit(5)
        ->get();

    return response()->json($data);
}
public function segmentation()
{
    $segment = DB::table('reports')
        ->join('ml_results', 'reports.id', '=', 'ml_results.report_id')
        ->where('ml_results.label', 'phishing')
        ->select('user_segment', DB::raw('COUNT(*) as total'))
        ->groupBy('user_segment')
        ->get();

    $region = DB::table('reports')
        ->join('ml_results', 'reports.id', '=', 'ml_results.report_id')
        ->where('ml_results.label', 'phishing')
        ->select('region', DB::raw('COUNT(*) as total'))
        ->groupBy('region')
        ->get();

    return response()->json([
        'segment' => $segment,
        'region' => $region
    ]);
}

}
