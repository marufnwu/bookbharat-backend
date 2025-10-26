<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceTemplateController extends Controller
{
    /**
     * Get all invoice templates
     */
    public function index()
    {
        try {
            $templates = InvoiceTemplate::orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $templates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoice templates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single invoice template
     */
    public function show($id)
    {
        try {
            $template = InvoiceTemplate::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $template,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice template not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Create new invoice template
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'header_html' => 'nullable|string',
                'footer_html' => 'nullable|string',
                'styles_css' => 'nullable|string',
                'thank_you_message' => 'nullable|string',
                'legal_disclaimer' => 'nullable|string',
                'logo_url' => 'nullable|url',
                'show_company_address' => 'boolean',
                'show_gst_number' => 'boolean',
                'is_active' => 'boolean',
                'is_default' => 'boolean',
                'custom_fields' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // If this is set as default, unset other defaults
            if ($request->is_default) {
                InvoiceTemplate::where('is_default', true)->update(['is_default' => false]);
            }

            $template = InvoiceTemplate::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Invoice template created successfully',
                'data' => $template,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoice template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update invoice template
     */
    public function update(Request $request, $id)
    {
        try {
            $template = InvoiceTemplate::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'header_html' => 'nullable|string',
                'footer_html' => 'nullable|string',
                'styles_css' => 'nullable|string',
                'thank_you_message' => 'nullable|string',
                'legal_disclaimer' => 'nullable|string',
                'logo_url' => 'nullable|url',
                'show_company_address' => 'boolean',
                'show_gst_number' => 'boolean',
                'is_active' => 'boolean',
                'is_default' => 'boolean',
                'custom_fields' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // If this is set as default, unset other defaults
            if ($request->is_default && !$template->is_default) {
                InvoiceTemplate::where('is_default', true)
                    ->where('id', '!=', $id)
                    ->update(['is_default' => false]);
            }

            $template->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Invoice template updated successfully',
                'data' => $template,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete invoice template
     */
    public function destroy($id)
    {
        try {
            $template = InvoiceTemplate::findOrFail($id);

            // Don't allow deleting the default template if it's the only one
            $templateCount = InvoiceTemplate::count();
            if ($template->is_default && $templateCount === 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the only template. Please create another template first.',
                ], 400);
            }

            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invoice template deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete invoice template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
