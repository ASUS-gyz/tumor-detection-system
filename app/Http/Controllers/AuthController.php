<?php

namespace App\Http\Controllers;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\User;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * 认证控制器
 *
 * 负责用户注册、登录、退出、个人信息管理、头像上传等功能。
 * 使用 Bearer Token 进行身份认证（自定义 Sanctum Guard）。
 */
class AuthController extends Controller
{
    // ───────────────────── 1. 患者注册 ─────────────────────

    /**
     * 患者注册
     *
     * POST /api/auth/register
     * 功能：患者自助注册账号，角色固定为 patient，返回用户信息及 API Token。
     * 参数：name, email, password, phone（可选）
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'patient',
            'phone' => $validated['phone'] ?? null,
            'status' => 'active',
        ]);

        // 生成 API Token
        $tokenResult = $user->createToken('auth_token');
        $plainTextToken = $tokenResult['plainTextToken'];

        return Result::success('注册成功', [
            'user' => $this->formatUser($user),
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    // ───────────────────── 2. 用户登录 ─────────────────────

    /**
     * 用户登录
     *
     * POST /api/auth/login
     * 功能：支持 patient / doctor / admin 三种角色登录，验证邮箱和密码。
     * 参数：email, password
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        // 账号不存在或密码错误
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw new BusinessException('邮箱或密码不正确', ResponseCode::PASSWORD_ERROR);
        }

        // 账号被禁用
        if ($user->status === 'disabled') {
            throw new BusinessException('账号已被禁用，请联系管理员', ResponseCode::ACCOUNT_DISABLED);
        }

        // 生成 API Token
        $tokenResult = $user->createToken('auth_token');
        $plainTextToken = $tokenResult['plainTextToken'];

        return Result::success('登录成功', [
            'user' => $this->formatUser($user),
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    // ───────────────────── 3. 退出登录 ─────────────────────

    /**
     * 退出登录
     *
     * POST /api/auth/logout
     * 功能：删除当前用户的所有 token。
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // 删除当前用户所有 token
        $user->tokens()->delete();

        return Result::success('退出成功');
    }

    // ───────────────────── 4. 获取当前用户信息 ─────────────────────

    /**
     * 获取当前登录用户信息
     *
     * GET /api/auth/me
     * 功能：返回当前登录用户的完整信息。
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return Result::success('成功', $this->formatUser($user));
    }

    // ───────────────────── 5. 修改密码 ─────────────────────

    /**
     * 修改密码
     *
     * PUT /api/auth/password
     * 功能：验证当前密码后更新为新密码。
     * 参数：current_password, new_password, new_password_confirmation
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // 验证当前密码
        if (! Hash::check($validated['current_password'], $user->password)) {
            throw new BusinessException('当前密码不正确', ResponseCode::PASSWORD_ERROR);
        }

        // 新密码不能与当前密码相同
        if (Hash::check($validated['new_password'], $user->password)) {
            throw new BusinessException('新密码不能与当前密码相同', ResponseCode::PARAM_ILLEGAL);
        }

        $user->password = $validated['new_password'];
        $user->save();

        return Result::success('密码修改成功');
    }

    // ───────────────────── 6. 更换头像 ─────────────────────

    /**
     * 更换头像（文件上传）
     *
     * POST /api/auth/avatar
     * 功能：接收图片文件，存储到 storage/app/public/avatars，更新用户 avatar_url。
     * 参数：avatar（图片文件，支持 jpg/jpeg/png/webp，最大 2MB）
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ], [
            'avatar.required' => '请选择头像文件',
            'avatar.image' => '文件须为图片格式',
            'avatar.mimes' => '头像仅支持 jpg、jpeg、png、webp 格式',
            'avatar.max' => '头像文件不能超过 2MB',
        ]);

        $user = $request->user();

        // 删除旧头像（避免文件堆积）
        if ($user->avatar_url) {
            $oldPath = str_replace('/storage/', '', parse_url($user->avatar_url, PHP_URL_PATH));
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // 存储新头像到 storage/app/public/avatars
        $path = $request->file('avatar')->store('avatars', 'public');

        // 生成公开访问 URL
        $user->avatar_url = asset('storage/' . $path);
        $user->save();

        return Result::success('头像更新成功', [
            'avatar_url' => $user->avatar_url,
        ]);
    }

    // ───────────────────── 7. 更新个人资料 ─────────────────────

    /**
     * 更新个人资料
     *
     * PUT /api/auth/profile
     * 功能：更新当前用户的姓名和手机号；医生角色额外可更新职称、专长等。
     * 参数：name, phone（可选）；医生还可传 title, specialty, department, introduction, experience_years
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // 基础字段：所有角色可更新
        $baseFields = ['name', 'phone'];
        $updateData = array_intersect_key($validated, array_flip($baseFields));

        // 医生专属字段：仅 doctor 角色可更新
        if ($user->role === 'doctor') {
            $doctorFields = array_intersect_key($validated, array_flip($request->doctorFields()));
            $updateData = array_merge($updateData, $doctorFields);
        }

        if (empty(array_filter($updateData, fn ($v) => $v !== null))) {
            throw new BusinessException('至少提供一个更新字段', ResponseCode::PARAM_ERROR);
        }

        $user->fill($updateData)->save();

        return Result::success('资料更新成功', $this->formatUser($user));
    }

    // ───────────────────── 辅助方法 ─────────────────────

    /**
     * 格式化用户返回数据
     */
    private function formatUser(User $user): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'created_at' => $user->created_at,
        ];

        // 医生额外字段
        if ($user->role === 'doctor') {
            $data['title'] = $user->title;
            $data['specialty'] = $user->specialty;
            $data['department'] = $user->department;
            $data['introduction'] = $user->introduction;
            $data['experience_years'] = $user->experience_years;
        }

        return $data;
    }

    // ───────────────────── 8. 忘记密码 ─────────────────────

    /**
     * 忘记密码 — 发送重置令牌
     *
     * POST /api/auth/forgot-password
     * 功能：根据邮箱发送密码重置令牌（模拟邮件发送）。
     * 参数：email
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
        ]);
        $user = User::where('email', $request->input('email'))->first();
        ], [
            'email.required' => '请输入邮箱地址',
            'email.email' => '邮箱格式不正确',
            'email.exists' => '该邮箱未注册',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        // 生成重置令牌（存入 password_reset_tokens 表）
        $token = \Illuminate\Support\Str::random(60);
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $token, 'created_at' => now()]
        );
        return Result::success('重置链接已发送', ['reset_token' => $token]);
    }


        return Result::success('重置链接已发送至您的邮箱', [
            'message' => '请检查邮箱中的重置链接（开发模式：token 直接返回）',
            'reset_token' => $token,
        ]);
    }

    // ───────────────────── 9. 重置密码 ─────────────────────

    /**
     * 重置密码
     *
     * POST /api/auth/reset-password
     * 功能：使用令牌重置密码。
     * 参数：email, token, password, password_confirmation
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
        ]);
        $record = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))->first();
        if (! $record || $record->token !== $request->input('token')) {
            throw new BusinessException('重置令牌无效或已过期', ResponseCode::PARAM_ILLEGAL);
        }
        $user = User::where('email', $request->input('email'))->first();
        $user->password = $request->input('password');
        $user->save();
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))->delete();
        return Result::success('密码重置成功');
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->email_verified_at) {
            return Result::success('邮箱已验证');
        }
        $user->email_verified_at = now();
        $user->save();
        return Result::success('邮箱验证成功');
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        if (! \Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)) {
            throw new BusinessException('密码不正确', ResponseCode::PASSWORD_ERROR);
        }
        $user->tokens()->delete();
        $user->status = 'disabled';
        $user->save();
        ], [
            'email.required' => '请输入邮箱地址',
            'token.required' => '令牌不能为空',
            'password.required' => '请输入新密码',
            'password.min' => '密码长度不能少于6位',
            'password.confirmed' => '两次输入的密码不一致',
        ]);

        // 验证令牌
        $record = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))
            ->first();

        if (! $record || $record->token !== $request->input('token')) {
            throw new BusinessException('重置令牌无效或已过期', ResponseCode::PARAM_ILLEGAL);
        }

        // 更新密码
        $user = User::where('email', $request->input('email'))->first();
        $user->password = $request->input('password');
        $user->save();

        // 删除已使用的令牌
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))
            ->delete();

        return Result::success('密码重置成功');
    }

    // ───────────────────── 10. 邮箱验证 ─────────────────────

    /**
     * 邮箱验证
     *
     * POST /api/auth/verify-email
     * 功能：标记当前用户邮箱为已验证。
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return Result::success('邮箱已验证');
        }

        $user->email_verified_at = now();
        $user->save();

        return Result::success('邮箱验证成功');
    }

    // ───────────────────── 11. 账号注销 ─────────────────────

    /**
     * 账号注销
     *
     * DELETE /api/auth/account
     * 功能：注销当前登录用户的账号（软标记为 disabled）。
     * 参数：password（确认身份）
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => '请输入密码以确认注销',
        ]);

        $user = $request->user();

        if (! \Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)) {
            throw new BusinessException('密码不正确', ResponseCode::PASSWORD_ERROR);
        }

        // 删除所有 token
        $user->tokens()->delete();

        // 标记账号为已禁用（软删除）
        $user->status = 'disabled';
        $user->save();

        return Result::success('账号已注销');
    }
}
