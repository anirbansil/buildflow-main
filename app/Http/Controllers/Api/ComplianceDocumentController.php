<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComplianceDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ComplianceDocumentController extends Controller
{
    /**
     * List compliance documents. The three required types (insurance, W-9, license)
     * are always present — as status "missing" when nothing has been uploaded yet.
     *
     * GET /api/v1/compliance-documents
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        if (! $company) {
            return response()->json(['data' => []]);
        }

        $documents = $company->complianceDocuments()->orderByDesc('updated_at')->get();

        $required = collect(ComplianceDocument::REQUIRED_TYPES)->map(function ($type) use ($documents) {
            $document = $documents->firstWhere('type', $type);

            return $document ? $this->resource($document) : $this->missingResource($type);
        });

        $others = $documents->where('type', 'other')->map(fn ($document) => $this->resource($document));

        return response()->json(['data' => $required->concat($others)->values()]);
    }

    /**
     * Upload a document. Send as multipart/form-data.
     * Uploading a required type again replaces the previous file; "other" documents accumulate.
     *
     * POST /api/v1/compliance-documents
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        if (! $company) {
            return response()->json(['message' => 'Create your company profile before uploading documents.'], 422);
        }

        $request->validate([
            'type' => 'required|string|in:'.implode(',', array_keys(ComplianceDocument::TYPE_LABELS)),
            'title' => 'required_if:type,other|nullable|string|max:255',
            'expiry_date' => 'nullable|date',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $path = $file->store("compliance_documents/{$company->id}", 'local');

            $attributes = [
                'uploaded_by' => $user->id,
                'title' => $request->input('title') ?: ComplianceDocument::TYPE_LABELS[$request->type],
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'expiry_date' => $request->expiry_date,
            ];

            $existing = $request->type === 'other'
                ? null
                : $company->complianceDocuments()->where('type', $request->type)->first();

            if ($existing) {
                Storage::disk('local')->delete($existing->file_path);
                $existing->update($attributes);
                $document = $existing;
            } else {
                $document = $company->complianceDocuments()->create($attributes + ['type' => $request->type]);
            }

            return response()->json([
                'message' => 'Document uploaded successfully.',
                'data' => $this->resource($document->fresh()),
            ], 201);

        } catch (\Exception $e) {
            Log::error('API upload compliance document: '.$e->getMessage());

            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    /**
     * Download a document (requires the bearer token).
     *
     * GET /api/v1/compliance-documents/{id}/download
     */
    public function download(Request $request, int $id): Response|JsonResponse
    {
        $document = ComplianceDocument::where('company_id', $request->user()->company_id)->find($id);

        if (! $document || ! Storage::disk('local')->exists($document->file_path)) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    private function resource(ComplianceDocument $document): array
    {
        $expired = $document->isExpired();

        return [
            'id' => $document->id,
            'type' => $document->type,
            'title' => $document->title,
            'status' => $expired ? 'expired' : 'valid',
            'status_label' => $expired ? 'Expired' : 'Valid',
            'expiry_date' => $document->expiry_date?->format('Y-m-d'),
            'file_name' => $document->file_name,
            'download_url' => url("api/v1/compliance-documents/{$document->id}/download"),
            'updated_at' => $document->updated_at?->toDateString(),
        ];
    }

    private function missingResource(string $type): array
    {
        return [
            'id' => null,
            'type' => $type,
            'title' => ComplianceDocument::TYPE_LABELS[$type],
            'status' => 'missing',
            'status_label' => 'Missing',
            'expiry_date' => null,
            'file_name' => null,
            'download_url' => null,
            'updated_at' => null,
        ];
    }
}
