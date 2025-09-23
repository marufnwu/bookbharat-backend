<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is admin or super-admin
        if (!$user->hasAnyRole(['admin', 'super-admin', 'manager'])) {
            throw ValidationException::withMessages([
                'email' => ['You do not have admin access.'],
            ]);
        }

        // Check if account is active
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been disabled.'],
            ]);
        }

        // Create token
        $token = $user->createToken('admin-token', ['admin'])->plainTextToken;

        // Load user with roles and permissions
        $user->load('roles.permissions');

        // Format the response
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'roles' => $user->roles->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'permissions' => $role->permissions->map(function ($permission) {
                                return [
                                    'id' => $permission->id,
                                    'name' => $permission->name,
                                    'guard_name' => $permission->guard_name
                                ];
                            })
                        ];
                    })
                ],
                'token' => $token,
                'expires_at' => now()->addDays(7)->toISOString()
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public function check(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['admin', 'super-admin', 'manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated as admin'
            ], 401);
        }

        // Load user with roles and permissions
        $user->load('roles.permissions');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'roles' => $user->roles->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'permissions' => $role->permissions->map(function ($permission) {
                                return [
                                    'id' => $permission->id,
                                    'name' => $permission->name,
                                    'guard_name' => $permission->guard_name
                                ];
                            })
                        ];
                    })
                ]
            ]
        ]);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['admin', 'super-admin', 'manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated as admin'
            ], 401);
        }

        // Delete current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $token = $user->createToken('admin-token', ['admin'])->plainTextToken;

        // Load user with roles and permissions
        $user->load('roles.permissions');

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'roles' => $user->roles->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'permissions' => $role->permissions->map(function ($permission) {
                                return [
                                    'id' => $permission->id,
                                    'name' => $permission->name,
                                    'guard_name' => $permission->guard_name
                                ];
                            })
                        ];
                    })
                ],
                'token' => $token,
                'expires_at' => now()->addDays(7)->toISOString()
            ]
        ]);
    }
}