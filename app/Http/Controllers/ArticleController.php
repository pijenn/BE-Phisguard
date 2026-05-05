<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ArticleController extends Controller
{
    #[OA\Post(
        path: "/articles",
        summary: "Create new educational article",
        tags: ["Articles"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["judul", "kategori_artikel", "isi_artikel", "rangkuman"],
                properties: [
                    new OA\Property(property: "judul", type: "string", example: "Waspada Phishing Via Email"),
                    new OA\Property(property: "kategori_artikel", type: "string", enum: ["Tips", "Modus", "Update Kasus"], example: "Tips"),
                    new OA\Property(property: "gambar", type: "string", format: "binary", description: "Image file (JPEG/PNG)"),
                    new OA\Property(property: "alt_text", type: "string", example: "Illustrasi email phishing"),
                    new OA\Property(property: "isi_artikel", type: "string", example: "Artikel lengkap tentang cara mendeteksi phishing..."),
                    new OA\Property(property: "rangkuman", type: "string", example: "Ringkasan singkat artikel"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Article created successfully"),
            new OA\Response(response: 422, description: "Validation failed")
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'kategori_artikel' => 'required|in:Tips,Modus,Update Kasus',
            'gambar' => 'required|string',
            'alt_text' => 'nullable|string|max:255',
            'isi_artikel' => 'required|string',
            'rangkuman' => 'required|string|max:500',
        ]);

        $article = Article::create([
            'judul' => $validated['judul'],
            'kategori_artikel' => $validated['kategori_artikel'],
            'alt_text' => $validated['alt_text'] ?? null,
            'isi_artikel' => $validated['isi_artikel'],
            'rangkuman' => $validated['rangkuman'],
            'created_by' => auth()->id(),
        ]);

        if ($request->hasFile('gambar')) {
            $path = $request->file('gambar')->store('articles', 'public');
            $article->update(['gambar' => $path]);
        }

        return response()->json([
            'message' => 'Article created successfully',
            'data' => $article
        ], 201);
    }

    #[OA\Patch(
        path: "/articles/{id}",
        summary: "Update educational article",
        tags: ["Articles"],
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
                    new OA\Property(property: "judul", type: "string", example: "Waspada Phishing Via Email"),
                    new OA\Property(property: "kategori_artikel", type: "string", enum: ["Tips", "Modus", "Update Kasus"]),
                    new OA\Property(property: "gambar", type: "string", format: "binary", description: "Image file (JPEG/PNG)"),
                    new OA\Property(property: "alt_text", type: "string"),
                    new OA\Property(property: "isi_artikel", type: "string"),
                    new OA\Property(property: "rangkuman", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Article updated successfully"),
            new OA\Response(response: 404, description: "Article not found"),
            new OA\Response(response: 422, description: "Validation failed")
        ]
    )]
    public function update(Request $request, $id)
    {
        $article = Article::find($id);

        if (!$article) {
            return response()->json(['message' => 'Article not found'], 404);
        }

        $validated = $request->validate([
            'judul' => 'nullable|string|max:255',
            'kategori_artikel' => 'nullable|in:Tips,Modus,Update Kasus',
            'gambar' => 'nullable|image|mimes:jpeg,png|max:5120',
            'alt_text' => 'nullable|string|max:255',
            'isi_artikel' => 'nullable|string',
            'rangkuman' => 'nullable|string|max:500',
        ]);

        $updateData = array_filter($validated, fn($value) => $value !== null);
        $updateData['updated_by'] = auth()->id();

        if ($request->hasFile('gambar')) {
            $path = $request->file('gambar')->store('articles', 'public');
            $updateData['gambar'] = $path;
        }

        $article->update($updateData);

        return response()->json([
            'message' => 'Article updated successfully',
            'data' => $article
        ]);
    }

    #[OA\Get(
        path: "/articles",
        summary: "Get all articles",
        tags: ["Articles"],
        parameters: [
            new OA\Parameter(
                name: "kategori",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["Tips", "Modus", "Update Kasus"]),
                description: "Filter by category"
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
            new OA\Response(response: 200, description: "List of articles")
        ]
    )]
    public function index(Request $request)
    {
        $query = Article::query();

        if ($request->has('kategori')) {
            $query->where('kategori_artikel', $request->kategori);
        }

        $articles = $query->paginate(10);

        return response()->json([
            'data' => $articles->items(),
            'pagination' => [
                'total' => $articles->total(),
                'per_page' => $articles->perPage(),
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
            ]
        ]);
    }

    #[OA\Get(
        path: "/articles/{id}",
        summary: "Get article by ID",
        tags: ["Articles"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Article detail"),
            new OA\Response(response: 404, description: "Article not found")
        ]
    )]
    public function show($id)
    {
        $article = Article::find($id);

        if (!$article) {
            return response()->json(['message' => 'Article not found'], 404);
        }

        return response()->json(['data' => $article]);
    }

    #[OA\Delete(
        path: "/articles/{id}",
        summary: "Delete article",
        tags: ["Articles"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Article deleted successfully"),
            new OA\Response(response: 404, description: "Article not found")
        ]
    )]
    public function destroy($id)
    {
        $article = Article::find($id);

        if (!$article) {
            return response()->json(['message' => 'Article not found'], 404);
        }

        $article->delete();

        return response()->json(['message' => 'Article deleted successfully']);
    }
}
