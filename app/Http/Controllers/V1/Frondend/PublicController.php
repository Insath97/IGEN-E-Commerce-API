<?php

namespace App\Http\Controllers\V1\Frondend;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    /* featured brand listing */
    public function featuredBrands(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);

            $brands = Brand::active()
                ->featured()
                ->ordered()
                ->limit($limit)
                ->get()->map->only(['id', 'name', 'logo']);

            if ($brands->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No featured brands found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Featured brands retrieved successfully',
                'data' => $brands
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve featured brands',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
