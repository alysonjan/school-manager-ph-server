<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthenticationController extends Controller
{
    // public function login(Request $request)
    // {
    //     $request->validate([
    //         'username' => 'required|string',
    //         'password' => 'required|string',
    //     ]);

    //     $key = 'login:' . strtolower($request->username) . '|' . $request->ip();

    //     if (RateLimiter::tooManyAttempts($key, 5)) {
    //         $seconds = RateLimiter::availableIn($key);
    //         return response()->json([
    //             'message' => 'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
    //         ], 429);
    //     }

    //     // Fetch user explicitly from users_main
    //     $user = DB::connection('users_main')->table('users')
    //         ->where('username', $request->username)
    //         ->first();

    //     if (!$user || !Hash::check($request->password, $user->password)) {
    //         RateLimiter::hit($key, 60);
    //         return response()->json([
    //             'message' => 'Invalid username or password.',
    //         ], 401);
    //     }

    //     RateLimiter::clear($key);

    //     // Fetch student profile from WLKA
    //     $studentProfile = DB::connection('wlka')
    //         ->table('student_records')
    //         ->where('user_id', $user->user_id)
    //         ->first();

    //     $fullName = $studentProfile->fullname ?? $user->username;
    //     $nickname = $studentProfile->nickname ?? '';

    //     $userData = [
    //         'user_id' => $user->user_id,
    //         'full_name' => $fullName,
    //         'username' => $user->username,
    //         'nickname' => $nickname,
    //         'email' => $user->email,
    //         'name' => $user->name ?? $user->username,
    //     ];

    //     // Redirect to /first-user if email empty (no token yet)
    //     if (empty($user->email) || trim($user->email) === '') {
    //         return response()->json([
    //             'success' => true,
    //             'redirect_to' => '/first-user',
    //             'message' => 'Please complete your profile setup',
    //             'user' => $userData,
    //             'requires_email' => true,
    //         ], 200);
    //     }

    //     // Create token after email check
    //     $userModel = User::on('users_main')->find($user->user_id);
    //     $userModel->tokens()->delete();
    //     $token = $userModel->createToken('auth_token')->plainTextToken;

    //     // ✅ Call reusable functions
    //     $this->createMessage($user->user_id, $fullName, "System Login - Kiosk Access", "User {$fullName} has successfully logged into the system kiosk.");
    //     $this->createAttendance($user->user_id, $fullName, now()->toTimeString(), 'Kiosk 1');

    //     return response()->json([
    //         'success' => true,
    //         'redirect_to' => '/',
    //         'message' => 'Login successful.',
    //         'user' => $userData,
    //         'token' => $token,
    //         'token_type' => 'Bearer',
    //         'requires_email' => false,
    //     ], 200);
    // }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $key = 'login:' . strtolower($request->username) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
            ], 429);
        }

        // Fetch user explicitly from users_main
        $user = DB::connection('users_main')->table('users')
            ->where('username', $request->username)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'message' => 'Invalid username or password.',
            ], 401);
        }

        RateLimiter::clear($key);

        // Fetch student profile from WLKA
        $studentProfile = DB::connection('wlka')
            ->table('student_records')
            ->where('user_id', $user->user_id)
            ->first();

        $fullName = $studentProfile->fullname ?? $user->username;
        $nickname = $studentProfile->nickname ?? '';

        $userData = [
            'user_id' => $user->user_id,
            'full_name' => $fullName,
            'username' => $user->username,
            'nickname' => $nickname,
            'email' => $user->email,
            'name' => $user->name ?? $user->username,
        ];

        // Redirect to /first-user if email empty (no token yet)
        if (empty($user->email) || trim($user->email) === '') {
            return response()->json([
                'success' => true,
                'redirect_to' => '/first-user',
                'message' => 'Please complete your profile setup',
                'user' => $userData,
                'requires_email' => true,
            ], 200);
        }

        // --- 🛑 SANCTUM TOKEN & EXPIRATION LOGIC (24 HOURS) 🛑 ---
        $userModel = User::on('users_main')->find($user->user_id);
        $userModel->tokens()->delete();

        // 1. Define 24 Hours expiration in minutes (24 hours * 60 minutes)
        $expirationMinutes = 1440; 

        // 2. Create the exact expiration time object
        $expiresAt = now()->addMinutes($expirationMinutes);

        // 3. Create the token object, explicitly setting the expiration date
        // The third parameter is the expiration timestamp
        $tokenObject = $userModel->createToken('auth_token', ['*'], $expiresAt);
        $token = $tokenObject->plainTextToken;

        // 4. Convert to Unix Timestamp in MILLISECONDS for the React Native client
        $expiresAtMs = $expiresAt ? $expiresAt->getTimestamp() * 1000 : null; 
        // --- 🛑 END SANCTUM LOGIC ---


        // ✅ Call reusable functions
        $this->createMessage($user->user_id, $fullName, "System Login - Kiosk Access", "User {$fullName} has successfully logged into the system kiosk.");
        $this->createAttendance($user->user_id, $fullName, now()->toTimeString(), 'Kiosk 1');

        return response()->json([
            'success' => true,
            'redirect_to' => '/',
            'message' => 'Login successful.',
            'user' => $userData,
            'token' => $token,
            'token_type' => 'Bearer',
            'requires_email' => false,
            'expires_at_ms' => $expiresAtMs, // Now returns the 24-hour expiration timestamp
        ], 200);
    }

    // ------------------- Reusable Functions -------------------

    private function createMessage($userId, $fullName, $subject, $message)
    {
        DB::connection('wlka')->table('messages')->insert([
            'user_id' => $userId,
            'date' => now()->toDateString(),
            'subject' => $subject,
            'message' => $message,
            'status' => 'unread',
            'full_name' => $fullName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAttendance($userId, $fullName, $timeIn, $kiosk)
    {
        DB::connection('wlka')->table('attendance_records')->insert([
            'user_id' => $userId,
            'date' => now()->toDateString(),
            'time_in' => $timeIn,
            'kiosk_terminal_in' => $kiosk,
            'time_out' => null,
            'kiosk_terminal_out' => null,
            'status' => 'unread',
            'full_name' => $fullName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
