<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;
use App\Traits\FileUploadTrait;
use Illuminate\Support\Facades\DB;


use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SettingController extends Controller implements HasMiddleware
{
    use FileUploadTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Setting Index', only: ['index']),
            new Middleware('permission:Setting Update', only: ['update']),
        ];
    }

    /**
     * Get all settings.
     */
    public function index()
    {
        try {
            $settings = Setting::all()->pluck('value', 'key');

            return response()->json([
                'status' => 'success',
                'message' => 'Settings retrieved successfully',
                'data' => $settings
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve settings',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Update settings.
     */
    public function update(UpdateSettingRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $filePaths = [];

            // Handle Logo Upload
            if ($request->hasFile('site_logo')) {
                $oldLogo = Setting::getValue('site_logo');
                $logoPath = $this->handleFileUpload($request, 'site_logo', $oldLogo, 'settings', 'logo');
                if ($logoPath) {
                    $data['site_logo'] = $logoPath;
                    $filePaths[] = $logoPath;
                }
            }

            // Handle Favicon Upload
            if ($request->hasFile('site_favicon')) {
                $oldFavicon = Setting::getValue('site_favicon');
                $faviconPath = $this->handleFileUpload($request, 'site_favicon', $oldFavicon, 'settings', 'favicon');
                if ($faviconPath) {
                    $data['site_favicon'] = $faviconPath;
                    $filePaths[] = $faviconPath;
                }
            }

            foreach ($data as $key => $value) {
                if ($value !== null) {
                    Setting::updateOrCreate(
                        ['key' => $key],
                        ['value' => $value]
                    );
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Settings updated successfully',
                'data' => Setting::all()->pluck('value', 'key')
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            // Clean up uploaded files if DB update failed
            if (!empty($filePaths)) {
                $this->deleteMultipleFiles($filePaths);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update settings',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
