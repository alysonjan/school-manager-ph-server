<?php

namespace App\Http\Controllers\api\attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    protected string $connectionName = 'wlka';

    // -------------------- Get Attendance Data --------------------
    public function attendance(Request $request)
    {
        try {
            $query = DB::connection($this->connectionName)->table('attendance_records');

            if ($request->has('startDate')) $query->where('date', '>=', $request->startDate);
            if ($request->has('endDate')) $query->where('date', '<=', $request->endDate);
            
            // ✅ REMOVED: Search functionality - we'll handle it client-side
            // if ($request->has('search') && $request->search) {
            //     $search = $request->search;
            //     $query->where(fn($q) =>
            //         $q->where('user_id', 'like', "%{$search}%")
            //           ->orWhere('kiosk_terminal_in','like',"%{$search}%")
            //           ->orWhere('kiosk_terminal_out','like',"%{$search}%")
            //     );
            // }
            
            if ($request->has('user_id') && $request->user_id) $query->where('user_id', $request->user_id);

            $data = $query->orderBy('date','desc')->orderBy('created_at','desc')->get();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance data: '.$e->getMessage()
            ], 500);
        }
    }

    // -------------------- Mark Attendance as Read --------------------
    public function markAttendanceAsRead($recordId)
    {
        try {
            $updated = DB::connection($this->connectionName)
                         ->table('attendance_records')
                         ->where('id', $recordId)
                         ->update(['status'=>'read','updated_at'=>now()]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => "Attendance record marked as read"
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Attendance record not found"
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to mark attendance as read: ".$e->getMessage()
            ], 500);
        }
    }

    public function markAllAttendanceAsRead()
    {
        try {
            $updated = DB::connection($this->connectionName)
                         ->table('attendance_records')
                         ->where('status','unread')
                         ->update(['status'=>'read','updated_at'=>now()]);

            return response()->json([
                'success' => true,
                'message' => "$updated attendance records marked as read"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to mark attendance as read: ".$e->getMessage()
            ], 500);
        }
    }
}