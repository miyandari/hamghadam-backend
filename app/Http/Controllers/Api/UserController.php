<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(User::with(['student','universityRoles.role','universityRoles.university'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->string('search')->toString();
                $q->where(fn($x) => $x->where('name','like',"%{$s}%")->orWhere('email','like',"%{$s}%"));
            })->latest()->paginate(25));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'=>['required','string','max:255'], 'email'=>['required','email','max:255','unique:users,email'],
            'password'=>['required','string','min:8','max:100'],
            'role'=>['required',Rule::in(['cultural_manager','student','observer'])],
            'university_id'=>['required','integer','exists:universities,id'],
        ]);
        if (empty($data['university_id']))
            return response()->json(['message'=>'برای تخصیص نقش، انتخاب دانشگاه الزامی است.'],422);
        $user = DB::transaction(function () use ($data) {
            $user=User::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>$data['password'],'is_active'=>true]);
            $role=Role::where('name',$data['role'])->firstOrFail();
            $user->universityRoles()->create(['university_id'=>$data['university_id'],'role_id'=>$role->id,'is_active'=>true]);
            return $user;
        });
        return response()->json(['message'=>'حساب کاربری ایجاد شد.','user'=>$user->load('universityRoles.role','universityRoles.university')],201);
    }

    public function show(User $user)
    { return response()->json(['user'=>$user->load(['student','universityRoles.role','universityRoles.university'])]); }

    public function update(Request $request, User $user)
    {
        abort_if($user->is_super_admin,403,'ویرایش حساب مدیر ارشد از این بخش مجاز نیست.');
        $data=$request->validate([
            'name'=>['sometimes','required','string','max:255'],
            'email'=>['sometimes','required','email','max:255',Rule::unique('users','email')->ignore($user->id)],
            'password'=>['sometimes','nullable','string','min:8','max:100'],
            'role'=>['sometimes',Rule::in(['cultural_manager','student','observer'])],
            'university_id'=>['sometimes','required','integer','exists:universities,id'],
        ]);
        DB::transaction(function () use ($data,$user) {
            $profile=array_filter(array_intersect_key($data,array_flip(['name','email','password'])),fn($v)=>$v!==null);
            if ($profile) $user->update($profile);
            if (array_key_exists('role',$data)||array_key_exists('university_id',$data)) {
                $old=$user->universityRoles()->with('role')->first();
                $roleName=$data['role']??$old?->role?->name;
                $universityId=$data['university_id']??$old?->university_id;
                if (!$universityId) abort(422,'برای تخصیص نقش، انتخاب دانشگاه الزامی است.');
                $user->universityRoles()->update(['is_active'=>false]);
                if ($roleName&&$universityId) {
                    $role=Role::where('name',$roleName)->firstOrFail();
                    $user->universityRoles()->updateOrCreate(['university_id'=>$universityId,'role_id'=>$role->id],['is_active'=>true]);
                }
            }
        });
        return response()->json(['message'=>'اطلاعات کاربر به‌روزرسانی شد.','user'=>$user->fresh()->load('universityRoles.role','universityRoles.university')]);
    }

    public function setActive(Request $request, User $user)
    {
        abort_if($user->is_super_admin,403,'وضعیت حساب مدیر ارشد قابل تغییر نیست.');
        $data=$request->validate(['is_active'=>['required','boolean']]);
        $user->update(['is_active'=>$data['is_active']]);
        if (!$data['is_active']) $user->tokens()->delete();
        return response()->json(['message'=>$data['is_active']?'حساب فعال شد.':'حساب غیرفعال شد.']);
    }
}
