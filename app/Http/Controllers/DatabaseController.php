<?php

namespace App\Http\Controllers;

use App\Services\DatabaseManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatabaseController extends Controller
{
    protected $dbManager;

    public function __construct(DatabaseManagerService $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    // -------------------- Databases --------------------

    public function index()
    {
        $databases = $this->dbManager->getAvailableDatabases();
        return response()->json([
            'success' => true,
            'databases' => $databases,
            'total' => count($databases)
        ]);
    }

    public function refresh()
    {
        $this->dbManager->refreshDatabases();
        return response()->json([
            'success' => true,
            'message' => 'Database list refreshed successfully'
        ]);
    }

    public function queryDatabase($databaseName)
    {
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json(['success' => false, 'message' => "Database '{$databaseName}' not found"], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            if (!$this->dbManager->testConnection($connectionName)) {
                return response()->json(['success' => false, 'message' => "Unable to connect to database '{$databaseName}'"], 500);
            }

            $tables = DB::connection($connectionName)->select("SHOW TABLES");
            $tableNames = array_map(fn($table) => array_values((array)$table)[0], $tables);

            return response()->json([
                'success' => true,
                'database' => $databaseName,
                'tables' => $tableNames,
                'table_count' => count($tableNames)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to query database: ' . $e->getMessage()], 500);
        }
    }

    public function getTableData($databaseName, $tableName, Request $request)
    {
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json(['success' => false, 'message' => "Database '{$databaseName}' not found"], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            $tableExists = DB::connection($connectionName)->select("SHOW TABLES LIKE ?", [$tableName]);

            if (empty($tableExists)) {
                return response()->json(['success' => false, 'message' => "Table '{$tableName}' not found in database '{$databaseName}'"], 404);
            }

            $perPage = $request->get('per_page', 50);
            $page = $request->get('page', 1);

            $data = DB::connection($connectionName)
                ->table($tableName)
                ->paginate($perPage, ['*'], 'page', $page);

            $columns = DB::connection($connectionName)->select("DESCRIBE `{$tableName}`");

            return response()->json([
                'success' => true,
                'database' => $databaseName,
                'table' => $tableName,
                'columns' => $columns,
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to get table data: ' . $e->getMessage()], 500);
        }
    }

    public function getDatabaseStats($databaseName)
    {
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json(['success' => false, 'message' => "Database '{$databaseName}' not found"], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);

            $stats = DB::connection($connectionName)
                ->select("
                    SELECT 
                        COUNT(*) as table_count,
                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as total_size_mb,
                        ROUND(SUM(data_length) / 1024 / 1024, 2) as data_size_mb,
                        ROUND(SUM(index_length) / 1024 / 1024, 2) as index_size_mb,
                        ROUND(SUM(data_free) / 1024 / 1024, 2) as free_size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = ?
                ", [$databaseName]);

            $tables = DB::connection($connectionName)
                ->select("
                    SELECT 
                        table_name,
                        table_rows,
                        ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb,
                        ROUND(data_length / 1024 / 1024, 2) as data_size_mb,
                        ROUND(index_length / 1024 / 1024, 2) as index_size_mb,
                        table_comment
                    FROM information_schema.tables 
                    WHERE table_schema = ?
                    ORDER BY (data_length + index_length) DESC
                ", [$databaseName]);

            return response()->json(['success' => true, 'database' => $databaseName, 'stats' => $stats[0] ?? null, 'tables' => $tables]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to get database statistics: ' . $e->getMessage()], 500);
        }
    }

    public function executeQuery($databaseName, Request $request)
    {
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json(['success' => false, 'message' => "Database '{$databaseName}' not found"], 404);
        }

        $query = $request->get('query');
        if (empty($query)) {
            return response()->json(['success' => false, 'message' => 'Query parameter is required'], 400);
        }

        $forbiddenKeywords = ['DROP','DELETE','TRUNCATE','ALTER','CREATE','INSERT','UPDATE','GRANT','REVOKE'];
        foreach ($forbiddenKeywords as $keyword) {
            if (str_contains(strtoupper($query), $keyword)) {
                return response()->json(['success' => false, 'message' => "Query type '{$keyword}' is not allowed"], 403);
            }
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            $result = DB::connection($connectionName)->select($query);
            return response()->json(['success' => true, 'database' => $databaseName, 'query' => $query, 'result' => $result, 'row_count' => count($result)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Query execution failed: ' . $e->getMessage()], 500);
        }
    }

    // -------------------- Attendance Records --------------------

    public function getAttendanceData($databaseName, Request $request)
    {
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json(['success' => false, 'message' => "Database '{$databaseName}' not found"], 404);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);
            $query = DB::connection($connectionName)->table('attendance_records');

            if ($request->has('startDate')) $query->where('date', '>=', $request->startDate);
            if ($request->has('endDate')) $query->where('date', '<=', $request->endDate);
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(fn($q) => $q->where('user_id', 'like', "%{$search}%")
                                             ->orWhere('kiosk_terminal_in','like',"%{$search}%")
                                             ->orWhere('kiosk_terminal_out','like',"%{$search}%"));
            }
            if ($request->has('user_id') && $request->user_id) $query->where('user_id', $request->user_id);

            $data = $query->orderBy('date','desc')->orderBy('created_at','desc')->get();
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch attendance data: '.$e->getMessage()], 500);
        }
    }

    // -------------------- Unified markAsRead --------------------

    public function markAsRead($databaseName, $table, $recordId = null)
    {
        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json(['success' => false, 'message' => "Database '{$databaseName}' not found"], 404);
        }

        $allowedTables = ['attendance_records', 'messages'];
        if (!in_array($table, $allowedTables)) {
            return response()->json(['success' => false, 'message' => "Table '{$table}' is not allowed"], 403);
        }

        try {
            $connectionName = $this->dbManager->getConnectionForDatabase($databaseName);

            if ($recordId) {
                $updated = DB::connection($connectionName)->table($table)->where('id', $recordId)->update(['status'=>'read','updated_at'=>now()]);
                if ($updated) return response()->json(['success'=>true,'message'=>ucfirst(str_replace('_',' ',$table))." record marked as read"]);
                return response()->json(['success'=>false,'message'=>ucfirst(str_replace('_',' ',$table))." record not found"],404);
            } else {
                $updated = DB::connection($connectionName)->table($table)->where('status','unread')->update(['status'=>'read','updated_at'=>now()]);
                return response()->json(['success'=>true,'message'=>"$updated ".str_replace('_',' ',$table)." marked as read"]);
            }
        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>"Failed to mark records as read: ".$e->getMessage()],500);
        }
    }

    // -------------------- Attendance Mark Read --------------------

    public function markAttendanceAsRead($databaseName, $recordId)
    {
        return $this->markAsRead($databaseName, 'attendance_records', $recordId);
    }

    public function markAllAttendanceAsRead($databaseName)
    {
        return $this->markAsRead($databaseName, 'attendance_records');
    }

    // -------------------- Messages --------------------

    public function getMessagesData($databaseName, Request $request)
    {
        $map = ['sm_db_users_main'=>'users_main','sm_db_wlka'=>'wlka'];
        $connectionName = $map[$databaseName] ?? $this->dbManager->getConnectionForDatabase($databaseName);

        if (!$this->dbManager->databaseExists($databaseName)) {
            return response()->json(['success' => false,'message' => "Database '{$databaseName}' not found"],404);
        }

        try {
            $query = DB::connection($connectionName)->table('messages');
            $userId = $request->get('user_id') ?? auth()->user()->user_id ?? null;
            if ($userId) $query->where('user_id',$userId);
            if ($request->has('startDate')) $query->where('date','>=',$request->startDate);
            if ($request->has('endDate')) $query->where('date','<=',$request->endDate);
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(fn($q) => $q->where('user_id','like',"%{$search}%")
                                           ->orWhere('subject','like',"%{$search}%")
                                           ->orWhere('message','like',"%{$search}%"));
            }

            $data = $query->orderByDesc('date')->orderByDesc('created_at')->get();
            if ($data->isEmpty()) return response()->json([
                'success'=>true,
                'message'=>["User {$userId} not found in messages.","No messages data available."],
                'data'=>[],
                'color'=>'red'
            ]);

            return response()->json(['success'=>true,'data'=>$data]);
        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>'Failed to fetch messages data: '.$e->getMessage()],500);
        }
    }

    public function markMessageAsRead($databaseName, $recordId)
    {
        return $this->markAsRead($databaseName, 'messages', $recordId);
    }

    public function markAllMessagesAsRead($databaseName)
    {
        return $this->markAsRead($databaseName, 'messages');
    }
}
