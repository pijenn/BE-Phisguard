<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\MlResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ReportController extends Controller
{
    #[OA\Post(
        path: "/report",
        summary: "Submit phishing report",
        tags: ["Report"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["channel_chat", "sender_account", "chat_text"],
                properties: [
                    new OA\Property(property: "channel_chat", type: "string", example: "WhatsApp"),
                    new OA\Property(property: "sender_account", type: "string", example: "08123456789"),
                    new OA\Property(property: "chat_text", type: "string", example: "Klik link hadiah ini"),
                    new OA\Property(property: "url", type: "string", example: "http://phishing.com"),
                    new OA\Property(property: "reporter_name", type: "string", example: "Pijen"),
                    new OA\Property(property: "region", type: "string", example: "Jakarta"),
                    new OA\Property(property: "interaksi", type: "bool", example: true),
                    new OA\Property(property: "incident_summary", type: "string", example: "Pengguna menerima pesan phishing dan mengklik link"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Report submitted successfully")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'channel_chat' => 'required|string',
            'sender_account' => 'required|string',
            'chat_text' => 'required|string',
        ]);

        $report = Report::create([
            'ticket' => Report::generateTicket(),
            'channel_chat' => $request->channel_chat,
            'sender_account' => $request->sender_account,
            'chat_text' => $request->chat_text,
            'url' => $request->url,
            'reporter_name' => $request->reporter_name,
            'region' => $request->region,
            'interaksi' => $request->interaksi,
            'incident_summary' => $request->incident_summary,
        ]);

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

    #[OA\Get(
        path: "/reports",
        summary: "Get all reports (accessible by all users)",
        tags: ["Report"],
        responses: [
            new OA\Response(response: 200, description: "List of reports")
        ]
    )]
    public function index()
    {
        return Report::with(['mlResult', 'adminActions'])->get();
    }

    #[OA\Get(
        path: "/report/{id}",
        summary: "Get report detail",
        tags: ["Report"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Report detail"),
            new OA\Response(response: 404, description: "Report not found")
        ]
    )]
    public function show($id)
    {
        $report = Report::with(['mlResult', 'adminActions'])->find($id);

        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        return $report;
    }

    #[OA\Get(
        path: "/admin/dashboard/weekly-trend",
        summary: "Get weekly phishing trend",
        tags: ["Dashboard"],
        responses: [
            new OA\Response(response: 200, description: "Weekly trend data")
        ]
    )]
    public function weeklyTrend()
    {
        return DB::table('reports')
            ->join('ml_results', 'reports.id', '=', 'ml_results.report_id')
            ->where('ml_results.label', 'phishing')
            ->selectRaw("DATE_FORMAT(reports.created_at, '%Y-%u') as week, COUNT(*) as total")
            ->groupBy('week')
            ->orderBy('week')
            ->get();
    }

    #[OA\Get(
        path: "/admin/dashboard/top-channel",
        summary: "Top phishing channels",
        tags: ["Dashboard"],
        responses: [
            new OA\Response(response: 200, description: "Top channels")
        ]
    )]
    public function topChannel()
    {
        return DB::table('reports')
            ->join('ml_results', 'reports.id', '=', 'ml_results.report_id')
            ->where('ml_results.label', 'phishing')
            ->select('channel_chat', DB::raw('COUNT(*) as total'))
            ->groupBy('channel_chat')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    #[OA\Get(
        path: "/admin/dashboard/top-modus",
        summary: "Top phishing modus",
        tags: ["Dashboard"],
        responses: [
            new OA\Response(response: 200, description: "Top modus")
        ]
    )]
    public function topModus()
    {
        return DB::table('reports')
            ->join('ml_results', 'reports.id', '=', 'ml_results.report_id')
            ->where('ml_results.label', 'phishing')
            ->select('modus_type', DB::raw('COUNT(*) as total'))
            ->groupBy('modus_type')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    #[OA\Get(
        path: "/admin/dashboard/segmentation",
        summary: "User and region segmentation",
        tags: ["Dashboard"],
        responses: [
            new OA\Response(response: 200, description: "Segmentation data")
        ]
    )]
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

        return [
            'segment' => $segment,
            'region' => $region
        ];
    }
}