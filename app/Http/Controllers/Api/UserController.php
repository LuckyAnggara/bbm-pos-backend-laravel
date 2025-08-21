<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User; // <-- Tambahkan ini juga
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::with('branch:id,name');

        // Filter berdasarkan branch_id jika ada di request
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Bisa ditambahkan paginasi jika perlu
        // $users = $query->latest()->paginate(15);
        $users = $query->latest()->get();

        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['admin', 'cashier'])],
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        // Load relasi branch untuk ditampilkan di detail
        return response()->json($user->load('branch'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|nullable|string|min:8',
            'role' => ['sometimes', 'required', Rule::in(['admin', 'cashier'])],
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        // Jika ada password baru, hash password tersebut
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        } else {
            // Hapus password dari array jika tidak diisi
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // Tambahkan logic otorisasi di sini, misal hanya admin yang boleh hapus
        // if (auth()->user()->role !== 'admin') {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $user->delete();

        return response()->json(null, 204);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'branch_id' => 'required|exists:branches,id', // Asumsi branch_id pertama adalah 1
        ]);

        try {
            $user = DB::transaction(function () use ($validated) {
                return User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => 'cashier', // Default role for public registration
                    'branch_id' => $validated['branch_id'],
                ]);
            });

            // Buat token untuk user yang baru dibuat
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
                'message' => 'Registration successful',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating user from registration: '.$e->getMessage());

            return response()->json(['message' => 'Registration failed. Please try again.'], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Log the user out (invalidate the token).
     */
    public function logout(Request $request)
    {
        // Gunakan guard 'web' untuk logout dari sesi stateful
        Auth::guard('web')->logout();

        // Invalidate sesi untuk keamanan
        $request->session()->invalidate();

        // Buat ulang token CSRF
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Successfully logged out']);
    }
}
