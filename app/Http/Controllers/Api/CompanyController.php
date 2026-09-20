<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    /**
     * Get the authenticated user's corporate entity (Owner Profile → Corporate Entity).
     *
     * GET /api/v1/company
     */
    public function show(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        return response()->json([
            'data' => $company ? $this->resource($company) : null,
        ]);
    }

    /**
     * Create the user's company on first call, update it afterwards.
     * legal_name is required when creating; on update only the fields sent are changed.
     *
     * POST /api/v1/company
     */
    public function save(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        $request->validate([
            'legal_name' => ($company ? 'sometimes|' : '').'required|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'registered_address' => 'nullable|string|max:1000',
            'business_type' => 'nullable|string|max:255',
            'primary_operations' => 'nullable|string|max:255',
        ]);

        try {
            $data = $request->only(['legal_name', 'tax_id', 'registered_address', 'business_type', 'primary_operations']);

            if ($company) {
                $company->update($data);
                $message = 'Company updated successfully.';
                $status = 200;
            } else {
                $company = Company::create($data);
                $user->update(['company_id' => $company->id]);
                $message = 'Company created successfully.';
                $status = 201;
            }

            return response()->json([
                'message' => $message,
                'data' => $this->resource($company->fresh()),
            ], $status);

        } catch (\Exception $e) {
            Log::error('API save company: '.$e->getMessage());

            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    private function resource(Company $company): array
    {
        $taxId = $company->tax_id;

        return [
            'id' => $company->id,
            'legal_name' => $company->legal_name,
            'tax_id_masked' => $taxId ? '**-***'.substr($taxId, -4) : null,
            'registered_address' => $company->registered_address,
            'business_type' => $company->business_type,
            'primary_operations' => $company->primary_operations,
            'created_at' => $company->created_at?->toDateTimeString(),
            'updated_at' => $company->updated_at?->toDateTimeString(),
        ];
    }
}
