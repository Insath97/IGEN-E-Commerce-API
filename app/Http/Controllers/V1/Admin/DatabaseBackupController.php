<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Traits\LogsActivity;

class DatabaseBackupController extends Controller implements HasMiddleware
{
    use LogsActivity;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Database Export', only: ['export']),
        ];
    }

    /**
     * Export the database as a SQL file.
     */
    public function export()
    {
        try {
            $dbConfig = config('database.connections.mysql');
            $database = $dbConfig['database'];
            $username = $dbConfig['username'];
            $password = $dbConfig['password'];
            $host = $dbConfig['host'];
            $port = $dbConfig['port'];

            $filename = "backup-" . $database . "-" . date('Y-m-d-H-i-s') . ".sql";
            $path = storage_path('app/' . $filename);

            // Construct the mysqldump command
            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s --port=%s %s > %s',
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($database),
                escapeshellarg($path)
            );

            // Execute the command
            $returnVar = null;
            $output = [];
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                throw new \Exception('Database backup failed. Ensure mysqldump is installed and in the system path. Exit code: ' . $returnVar);
            }

            if (!file_exists($path)) {
                throw new \Exception('Database backup file was not created.');
            }

            $this->logActivity('System', 'Database Export', "Exported database backup: {$filename}");

            return response()->download($path)->deleteFileAfterSend(true);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export database',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
