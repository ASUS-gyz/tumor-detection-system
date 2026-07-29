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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::create([
            'name' => $validated['name'], 'email' => $validated['email'],
            'password' => $validated['password'], 'role' => 'patient',
            'phone' => $validated['phone'] ?? null, 'status' => 'active',
        ]);
        $tokenResult = $user->createToken('auth_token');
        return Result::success('注册成功', [
            'user' => $this->formatUser($user),
            'token' => $tokenResult['plainTextToken'], 'token_type' => 'Bearer',
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::where('email', $validated['email'])->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw new BusinessException('邮箱或密码不正确', ResponseCode::PASSWORD_ERROR);
        }
        if ($user->status === 'disabled') {
            throw new BusinessException('账号已被禁用，请联系管理员', ResponseCode::ACCOUNT_DISABLED);
        }
        $tokenResult = $user->createToken('auth_token');
        return Result::success('登录成功', [
            'user' => $this->formatUser($user),
            'token' => $tokenResult['plainTextToken'], 'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        return Result::success('退出成功');
    }

    public function me(Request $request): JsonResponse
    {
        return Result::success('成功', $this->formatUser($request->user()));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        if (! Hash::check($validated['current_password'], $user->password)) {
            throw new BusinessException('当前密码不正确', ResponseCode::PASSWORD_ERROR);
        }
        if (Hash::check($validated['new_password'], $user->password)) {
            throw new BusinessException('新密码不能与当前密码相同', ResponseCode::PARAM_ILLEGAL);
        }
        $user->password = $validated['new_password'];
        $user->save();
        return Result::success('密码修改成功');
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'avatar.required' => '请选择头像文件',
            'avatar.image' => '文件须为图片格式',
            'avatar.mimes' => '头像仅支持 jpg、jpeg、png、webp 格式',
            'avatar.max' => '头像文件不能超过 2MB',
        ]);
        $user = $request->user();
        if ($user->avatar_url) {
            $oldPath = str_replace('/storage/', '', parse_url($user->avatar_url, PHP_URL_PATH));
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar_url = asset('storage/' . $path);
        $user->save();
        return Result::success('头像更新成功', ['avatar_url' => $user->avatar_url]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $baseFields = ['name', 'phone'];
        $updateData = array_intersect_key($validated, array_flip($baseFields));
        if ($user->role === 'doctor') {
            $doctorFields = array_intersect_key($validated, array_flip(
                ['title', 'specialty', 'department', 'introduction', 'experience_years']
            ));
            $updateData = array_merge($updateData, $doctorFields);
        }
        if (empty(array_filter($updateData, fn ($v) => $v !== null))) {
            throw new BusinessException('至少提供一个更新字段', ResponseCode::PARAM_ERROR);
        }
        $user->fill($updateData)->save();
        return Result::success('资料更新成功', $this->formatUser($user));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
        ], [
            'email.required' => '请输入邮箱地址',
            'email.email' => '邮箱格式不正确',
            'email.exists' => '该邮箱未注册',
        ]);
        $user = User::where('email', $request->input('email'))->first();
        $token = Str::random(60);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $token, 'created_at' => now()]
        );
<<<<<<< Updated upstream

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
=======
        return Result::success('重置链接已发送', ['reset_token' => $token]);
    }

>>>>>>> Stashed changes
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
<<<<<<< Updated upstream
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
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

=======
            'password' => [
                'required', 'string', 'min:8', 'max:100', 'confirmed',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $types = 0;
                    if (preg_match('/[A-Z]/', (string) $value)) { $types++; }
                    if (preg_match('/[a-z]/', (string) $value)) { $types++; }
                    if (preg_match('/[0-9]/', (string) $value)) { $types++; }
                    if (preg_match('/[!@#$%^&*()_+\-=\[\]{}|;\':",.\/?]/', (string) $value)) { $types++; }
                    if ($types < 3) { $fail('密码必须包含大写字母、小写字母、数字、特殊符号中至少3种类型'); }
                },
            ],
        ]);
        $record = DB::table('password_reset_tokens')->where('email', $request->input('email'))->first();
        if (! $record || $record->token !== $request->input('token')) {
            throw new BusinessException('重置令牌无效或已过期', ResponseCode::PARAM_ILLEGAL);
        }
        $user = User::where('email', $request->input('email'))->first();
        $user->password = $request->input('password');
        $user->save();
        DB::table('password_reset_tokens')->where('email', $request->input('email'))->delete();
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
        if (! Hash::check($request->input('password'), $user->password)) {
            throw new BusinessException('密码不正确', ResponseCode::PASSWORD_ERROR);
        }
        $user->tokens()->delete();
        $user->status = 'disabled';
        $user->save();
>>>>>>> Stashed changes
        return Result::success('账号已注销');
    }

    private function formatUser(User $user): array
    {
        $data = [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'role' => $user->role, 'phone' => $user->phone, 'avatar_url' => $user->avatar_url,
            'status' => $user->status, 'created_at' => $user->created_at,
        ];
        if ($user->role === 'doctor') {
            $data['title'] = $user->title;
            $data['specialty'] = $user->specialty;
            $data['department'] = $user->department;
            $data['introduction'] = $user->introduction;
            $data['experience_years'] = $user->experience_years;
        }
        return $data;
    }
}
