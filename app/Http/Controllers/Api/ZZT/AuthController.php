<?php

namespace App\Http\Controllers\Api\ZZT;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ZZT\ChangePasswordRequest;
use App\Http\Requests\ZZT\LoginRequest;
use App\Http\Requests\ZZT\RegisterRequest;
use App\Http\Requests\ZZT\UpdateProfileRequest;
use App\Models\User;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $v = $request->validated();
        $user = User::create(['name' => $v['name'], 'email' => $v['email'], 'password' => $v['password'], 'role' => 'patient', 'phone' => $v['phone'] ?? null, 'status' => 'active']);
        $t = $user->createToken('auth_token');
        return Result::success('注册成功', ['user' => $this->fmt($user), 'token' => $t['plainTextToken'], 'token_type' => 'Bearer']);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $v = $request->validated();
        $user = User::where('email', $v['email'])->first();
        if (! $user || ! Hash::check($v['password'], $user->password)) { throw new BusinessException('邮箱或密码不正确', ResponseCode::PASSWORD_ERROR); }
        if ($user->status === 'disabled') { throw new BusinessException('账号已被禁用', ResponseCode::ACCOUNT_DISABLED); }
        $t = $user->createToken('auth_token');
        return Result::success('登录成功', ['user' => $this->fmt($user), 'token' => $t['plainTextToken'], 'token_type' => 'Bearer']);
    }

    public function logout(Request $request): JsonResponse
    { $request->user()->tokens()->delete(); return Result::success('退出成功'); }

    public function me(Request $request): JsonResponse
    { return Result::success('成功', $this->fmt($request->user())); }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $v = $request->validated(); $user = $request->user();
        if (! Hash::check($v['current_password'], $user->password)) { throw new BusinessException('当前密码不正确', ResponseCode::PASSWORD_ERROR); }
        if (Hash::check($v['new_password'], $user->password)) { throw new BusinessException('新密码不能与当前密码相同', ResponseCode::PARAM_ILLEGAL); }
        $user->password = $v['new_password']; $user->save();
        return Result::success('密码修改成功');
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);
        $user = $request->user();
        if ($user->avatar_url) { $o = str_replace('/storage/', '', parse_url($user->avatar_url, PHP_URL_PATH)); if ($o && Storage::disk('public')->exists($o)) Storage::disk('public')->delete($o); }
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar_url = asset('storage/' . $path); $user->save();
        return Result::success('头像更新成功', ['avatar_url' => $user->avatar_url]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $v = $request->validated(); $user = $request->user();
        $d = array_intersect_key($v, array_flip(['name', 'phone']));
        if ($user->role === 'doctor') $d = array_merge($d, array_intersect_key($v, array_flip(['title', 'specialty', 'department', 'introduction', 'experience_years'])));
        if (empty(array_filter($d, fn($x) => $x !== null))) throw new BusinessException('至少提供一个更新字段', ResponseCode::PARAM_ERROR);
        $user->fill($d)->save();
        return Result::success('资料更新成功', $this->fmt($user));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|exists:users,email']);
        $user = User::where('email', $request->input('email'))->first();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('password_reset_tokens')->updateOrInsert(['email' => $user->email], ['token' => $code, 'created_at' => now()]);
        \Illuminate\Support\Facades\Mail::raw("您的密码重置验证码为：{$code}\n\n请在60分钟内使用。", fn($m) => $m->to($user->email)->subject('密码重置验证码'));
        \Illuminate\Support\Facades\Log::info('密码重置验证码', ['email' => $user->email, 'code' => $code]);
        return Result::success('验证码已发送');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email', 'token' => 'required|string',
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed', function ($a, $v, $f) { $t = 0; if (preg_match('/[A-Z]/', $v)) $t++; if (preg_match('/[a-z]/', $v)) $t++; if (preg_match('/[0-9]/', $v)) $t++; if (preg_match('/[!@#$%^&*()_+\-=\[\]{}|;\':",.\/?]/', $v)) $t++; if ($t < 3) $f('密码需包含大写、小写、数字、特殊符号中至少3种'); }],
        ]);
        $r = DB::table('password_reset_tokens')->where('email', $request->input('email'))->first();
        if (! $r || $r->token !== $request->input('token')) throw new BusinessException('令牌无效', ResponseCode::PARAM_ILLEGAL);
        if (now()->diffInMinutes($r->created_at) > 60) { DB::table('password_reset_tokens')->where('email', $request->input('email'))->delete(); throw new BusinessException('令牌已过期', ResponseCode::PARAM_ILLEGAL); }
        User::where('email', $request->input('email'))->first()->update(['password' => $request->input('password')]);
        DB::table('password_reset_tokens')->where('email', $request->input('email'))->delete();
        return Result::success('密码重置成功');
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']); $user = $request->user();
        if (! Hash::check($request->input('password'), $user->password)) throw new BusinessException('密码不正确', ResponseCode::PASSWORD_ERROR);
        $user->tokens()->delete(); $user->status = 'disabled'; $user->save();
        return Result::success('账号已注销');
    }

    private function fmt(User $u): array
    {
        $d = ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'phone' => $u->phone, 'avatar_url' => $u->avatar_url, 'status' => $u->status, 'created_at' => $u->created_at];
        if ($u->role === 'doctor') { $d['title'] = $u->title; $d['specialty'] = $u->specialty; $d['department'] = $u->department; $d['introduction'] = $u->introduction; $d['experience_years'] = $u->experience_years; }
        return $d;
    }
}
