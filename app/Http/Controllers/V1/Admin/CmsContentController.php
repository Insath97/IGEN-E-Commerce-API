<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCmsContentRequest;
use App\Models\CmsContent;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CmsContentController extends Controller implements HasMiddleware
{
    use FileUploadTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:CMS Index', only: ['index']),
            new Middleware('permission:CMS Update', only: ['update']),
        ];
    }

    /**
     * Get all CMS content.
     */
    public function index(Request $request)
    {
        try {
            $page = $request->query('page');
            $query = CmsContent::query();

            if ($page) {
                $query->where('page', $page);
            }

            $contents = $query->get()->groupBy(['page', 'section']);

            return response()->json([
                'status' => 'success',
                'message' => 'CMS contents retrieved successfully',
                'data' => $contents
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve CMS contents',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update or create CMS content.
     */
    public function update(UpdateCmsContentRequest $request)
    {
        try {
            DB::beginTransaction();

            $contents = $request->validated()['contents'];
            $updatedContents = [];

            foreach ($contents as $index => $item) {
                $page = $item['page'];
                $section = $item['section'];
                $key = $item['key'];
                $type = $item['type'];
                $value = $item['value'];
                $label = $item['label'] ?? null;

                // Find existing content to handle file replacement
                $existing = CmsContent::where('page', $page)
                    ->where('section', $section)
                    ->where('key', $key)
                    ->first();

                if ($type === 'image' && $request->hasFile("contents.{$index}.value")) {
                    $oldPath = $existing ? $existing->value : null;
                    $value = $this->handleFileUpload(
                        $request, 
                        "contents.{$index}.value", 
                        $oldPath, 
                        "cms/{$page}/{$section}", 
                        $key
                    );
                }

                $content = CmsContent::updateOrCreate(
                    [
                        'page' => $page,
                        'section' => $section,
                        'key' => $key
                    ],
                    [
                        'value' => $value,
                        'type' => $type,
                        'label' => $label ?? ($existing ? $existing->label : null)
                    ]
                );

                $updatedContents[] = $content;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'CMS contents updated successfully',
                'data' => $updatedContents
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update CMS contents',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
