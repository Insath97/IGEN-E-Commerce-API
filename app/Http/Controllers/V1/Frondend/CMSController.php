<?php

namespace App\Http\Controllers\V1\Frondend;

use App\Http\Controllers\Controller;
use App\Models\CmsContent;

class CMSController extends Controller
{
    /**
     * Get content for a specific page.
     */
    public function getPageContent(string $page)
    {
        try {
            $content = CmsContent::getStructuredPageContent($page);

            if (empty($content)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No content found for this page',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Page content retrieved successfully',
                'data' => $content
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve page content',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
