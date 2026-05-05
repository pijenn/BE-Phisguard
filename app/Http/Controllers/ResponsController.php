<?php

namespace App\Http\Controllers;

use App\Models\Respons;
use App\Models\Report;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ResponsController extends Controller
{
    #[OA\Post(
        path: "/admin/respons",
        summary: "Create admin response for report",
        tags: ["Admin Response"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["report_id", "hasil_keputusan"],
                properties: [
                    new OA\Property(property: "report_id", type: "integer", example: 1),
                    new OA\Property(property: "hasil_keputusan", type: "string", enum: ["Confirm Valid Phishing", "False Positive", "Need More Info"], example: "Confirm Valid Phishing"),
                    new OA\Property(property: "kategori", type: "string", enum: [
                        "Mengatasnamakan Bank",
                        "E-Wallet / Fintech",
                        "OTP / Verifikasi Akun",
                        "Hadiah / Undian",
                        "Paket / Kurir",
                        "Customer Service Palsu",
                        "Investasi Bodong",
                        "Akun Marketplace",
                        "Typosquatting / Domain Palsu",
                        "Lainnya"
                    ], example: "Mengatasnamakan Bank"),
                    new OA\Property(property: "catatan", type: "string", example: "Telah diverifikasi sebagai phishing valid"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Response created successfully"),
            new OA\Response(response: 422, description: "Validation failed")
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:reports,id',
            'hasil_keputusan' => 'required|in:Confirm Valid Phishing,False Positive,Need More Info',
            'kategori' => 'nullable|in:Mengatasnamakan Bank,E-Wallet / Fintech,OTP / Verifikasi Akun,Hadiah / Undian,Paket / Kurir,Customer Service Palsu,Investasi Bodong,Akun Marketplace,Typosquatting / Domain Palsu,Lainnya',
            'catatan' => 'nullable|string|max:1000',
        ]);

        $respons = Respons::create([
            'report_id' => $validated['report_id'],
            'hasil_keputusan' => $validated['hasil_keputusan'],
            'kategori' => $validated['kategori'] ?? null,
            'catatan' => $validated['catatan'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Response created successfully',
            'data' => $respons
        ], 201);
    }

    #[OA\Get(
        path: "/respons",
        summary: "Get all responses",
        tags: ["Response"],
        parameters: [
            new OA\Parameter(
                name: "report_id",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer"),
                description: "Filter by report ID"
            ),
            new OA\Parameter(
                name: "hasil_keputusan",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["Confirm Valid Phishing", "False Positive", "Need More Info"]),
                description: "Filter by decision result"
            ),
            new OA\Parameter(
                name: "page",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer"),
                description: "Page number"
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "List of responses")
        ]
    )]
    public function index(Request $request)
    {
        $query = Respons::with('report');

        if ($request->has('report_id')) {
            $query->where('report_id', $request->report_id);
        }

        if ($request->has('hasil_keputusan')) {
            $query->where('hasil_keputusan', $request->hasil_keputusan);
        }

        $respons = $query->paginate(10);

        return response()->json([
            'data' => $respons->items(),
            'pagination' => [
                'total' => $respons->total(),
                'per_page' => $respons->perPage(),
                'current_page' => $respons->currentPage(),
                'last_page' => $respons->lastPage(),
            ]
        ]);
    }

    #[OA\Get(
        path: "/respons/{id}",
        summary: "Get response by ID",
        tags: ["Response"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Response detail"),
            new OA\Response(response: 404, description: "Response not found")
        ]
    )]
    public function show($id)
    {
        $respons = Respons::with('report')->find($id);

        if (!$respons) {
            return response()->json(['message' => 'Response not found'], 404);
        }

        return response()->json(['data' => $respons]);
    }

    #[OA\Patch(
        path: "/admin/respons/{id}",
        summary: "Update response",
        tags: ["Admin Response"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "hasil_keputusan", type: "string", enum: ["Confirm Valid Phishing", "False Positive", "Need More Info"]),
                    new OA\Property(property: "kategori", type: "string"),
                    new OA\Property(property: "catatan", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Response updated successfully"),
            new OA\Response(response: 404, description: "Response not found")
        ]
    )]
    public function update(Request $request, $id)
    {
        $respons = Respons::find($id);

        if (!$respons) {
            return response()->json(['message' => 'Response not found'], 404);
        }

        $validated = $request->validate([
            'hasil_keputusan' => 'nullable|in:Confirm Valid Phishing,False Positive,Need More Info',
            'kategori' => 'nullable|in:Mengatasnamakan Bank,E-Wallet / Fintech,OTP / Verifikasi Akun,Hadiah / Undian,Paket / Kurir,Customer Service Palsu,Investasi Bodong,Akun Marketplace,Typosquatting / Domain Palsu,Lainnya',
            'catatan' => 'nullable|string|max:1000',
        ]);

        $updateData = array_filter($validated, fn($value) => $value !== null);
        $updateData['updated_by'] = auth()->id();

        $respons->update($updateData);

        return response()->json([
            'message' => 'Response updated successfully',
            'data' => $respons
        ]);
    }

    #[OA\Delete(
        path: "/admin/respons/{id}",
        summary: "Delete response",
        tags: ["Admin Response"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Response deleted successfully"),
            new OA\Response(response: 404, description: "Response not found")
        ]
    )]
    public function destroy($id)
    {
        $respons = Respons::find($id);

        if (!$respons) {
            return response()->json(['message' => 'Response not found'], 404);
        }

        $respons->delete();

        return response()->json(['message' => 'Response deleted successfully']);
    }
}
