<?php

namespace App\Http\Controllers\api\messages;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessagesController extends Controller
{
    protected string $connectionName = 'wlka';
    
    // Fixed getMessagesData method with better search
    public function getMessagesData(Request $request)
    {
        try {
            $query = DB::connection($this->connectionName)->table('messages');

            // Get user_id either from request or authenticated user
            $userId = $request->get('user_id') ?? auth()->user()->user_id ?? null;
            if ($userId) {
                $query->where('user_id', $userId);
            }

            // Filter by date if needed
            if ($request->has('startDate') && $request->startDate) {
                $query->where('date', '>=', $request->startDate);
            }
            if ($request->has('endDate') && $request->endDate) {
                $query->where('date', '<=', $request->endDate);
            }

            // Remove search from backend - we'll handle it client-side
            // if ($request->has('search') && !empty(trim($request->search))) {
            //     $search = trim($request->search);
            //     $query->where(function($q) use ($search) {
            //         $q->where('subject', 'like', "%{$search}%")
            //           ->orWhere('message', 'like', "%{$search}%")
            //           ->orWhere('status', 'like', "%{$search}%");
            //     });
            // }

            $data = $query->orderByDesc('date')->orderByDesc('created_at')->get();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch messages: ' . $e->getMessage()
            ], 500);
        }
    }

    // Add this method to your MessagesController for testing
public function testSearch(Request $request)
{
    try {
        Log::info('🧪 TEST SEARCH REQUEST', $request->all());
        
        $query = DB::connection($this->connectionName)->table('messages');
        
        // Get user_id
        $userId = $request->get('user_id') ?? auth()->user()->user_id ?? null;
        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Test search
        if ($request->has('search') && !empty(trim($request->search))) {
            $search = trim($request->search);
            
            Log::info('🧪 SEARCHING FOR:', ['term' => $search]);
            
            $query->where(function($q) use ($search) {
                $q->where('subject', 'LIKE', "%{$search}%")
                  ->orWhere('message', 'LIKE', "%{$search}%")
                  ->orWhere('status', 'LIKE', "%{$search}%");
            });
        }

        $sql = $query->toSql();
        $bindings = $query->getBindings();
        
        $results = $query->get();
        
        Log::info('🧪 TEST SEARCH RESULTS', [
            'search_term' => $request->search,
            'sql_query' => $sql,
            'bindings' => $bindings,
            'results_count' => $results->count(),
            'sample_results' => $results->take(3)
        ]);

        return response()->json([
            'success' => true,
            'search_term' => $request->search,
            'query' => $sql,
            'bindings' => $bindings,
            'results_count' => $results->count(),
            'data' => $results
        ]);
    } catch (\Exception $e) {
        Log::error('🧪 TEST SEARCH ERROR:', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Test failed: ' . $e->getMessage()
        ], 500);
    }
}

    // -------------------- Get Unread Count --------------------
    public function getUnreadCount(Request $request)
    {
        try {
            $query = DB::connection($this->connectionName)->table('messages')
                ->where('status', 'unread');

            // Get user_id either from request or authenticated user
            $userId = $request->get('user_id') ?? auth()->user()->user_id ?? null;
            
            // Make sure we filter by user_id if it exists
            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                // If no user_id is provided, return 0 to avoid counting all messages
                return response()->json([
                    'success' => true,
                    'data' => [
                        'unread_count' => 0
                    ]
                ]);
            }

            $unreadCount = $query->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $unreadCount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unread count: ' . $e->getMessage()
            ], 500);
        }
    }

    // -------------------- Mark Message as Read --------------------
    public function markMessageAsRead($recordId)
    {
        return $this->markAsRead($recordId);
    }

    public function markAllMessagesAsRead()
    {
        return $this->markAsRead();
    }

    // -------------------- Helper --------------------
    protected function markAsRead($recordId = null)
    {
        try {
            if ($recordId) {
                $updated = DB::connection($this->connectionName)
                    ->table('messages')
                    ->where('id', $recordId)
                    ->update(['status' => 'read', 'updated_at' => now()]);

                return response()->json([
                    'success' => (bool)$updated,
                    'message' => $updated ? 'Message marked as read' : 'Message not found'
                ]);
            } else {
                $updated = DB::connection($this->connectionName)
                    ->table('messages')
                    ->where('status', 'unread')
                    ->update(['status' => 'read', 'updated_at' => now()]);

                return response()->json([
                    'success' => true,
                    'message' => "$updated messages marked as read"
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to mark message(s) as read: " . $e->getMessage()
            ], 500);
        }
    }
}