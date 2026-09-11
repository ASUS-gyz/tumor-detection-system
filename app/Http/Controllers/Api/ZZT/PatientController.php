<?php

namespace App\Http\Controllers\Api\ZZT;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ZZT\CreateAppointmentRequest;
use App\Http\Requests\ZZT\AppointmentListRequest;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $pid = $request->user()->id;
        return Result::success('成功', [
            'pending_count' => Appointment::where('patient_id', $pid)->whereIn('status', ['pending', 'called', 'in_progress'])->count(),
            'completed_count' => Appointment::where('patient_id', $pid)->where('status', 'completed')->count(),
            'ai_diagnosis_count' => \App\Models\AIDiagnosis::where('patient_id', $pid)->where('type', 'text')->count(),
            'next_appointment' => $this->fmtSimple(Appointment::with('doctor:id,name,title,department')->where('patient_id', $pid)->whereIn('status', ['pending', 'called'])->orderBy('appointment_date')->orderBy('appointment_time')->first()),
        ]);
    }

    public function doctors(Request $request): JsonResponse
    {
        $q = User::where('role', 'doctor')->where('status', 'active');
        if ($kw = $request->input('keyword')) $q->where(fn($q) => $q->where('name', 'like', "%{$kw}%")->orWhere('title', 'like', "%{$kw}%")->orWhere('specialty', 'like', "%{$kw}%")->orWhere('department', 'like', "%{$kw}%"));
        $p = $q->select('id', 'name', 'title', 'specialty', 'department', 'introduction', 'experience_years', 'avatar_url')->orderBy('id')->paginate(min((int) $request->input('per_page', 10), 50));
        return Result::success('成功', ['list' => $p->items(), 'page' => $p->currentPage(), 'size' => $p->perPage(), 'total' => $p->total(), 'total_pages' => $p->lastPage()]);
    }

    public function doctorDetail(int $id): JsonResponse
    {
        $d = User::where('role', 'doctor')->where('status', 'active')->select('id', 'name', 'title', 'specialty', 'department', 'introduction', 'experience_years', 'avatar_url', 'phone')->find($id);
        if (! $d) throw new BusinessException('医生不存在或已停诊', ResponseCode::DATA_NOT_FOUND);
        return Result::success('成功', $d);
    }

    public function store(CreateAppointmentRequest $request): JsonResponse
    {
        $v = $request->validated(); $pid = $request->user()->id;
        $a = DB::transaction(function () use ($v, $pid) {
            if (Appointment::where('patient_id', $pid)->whereIn('status', ['pending', 'called', 'in_progress'])->exists()) throw new BusinessException('您已有一个进行中的预约', ResponseCode::DUPLICATE_SUBMIT);
            $this->assertSlotBookable((int) $v['doctor_id'], $v['appointment_date'], $v['appointment_time']);
            return Appointment::create(['patient_id' => $pid, 'doctor_id' => $v['doctor_id'], 'appointment_date' => $v['appointment_date'], 'appointment_time' => $v['appointment_time'], 'status' => 'pending']);
        });
        $a->load('doctor:id,name,title,department');
        return Result::success('预约成功', $this->fmt($a));
    }

    /**
     * 排班约束：停诊/时段开放/时段占用/当日上限。
     * 以该医生该星期几的排班行为锁载体（无则原子补建默认行），保证并发预约串行。
     */
    private function assertSlotBookable(int $doctorId, string $date, string $time): void
    {
        $dow = (int) date('w', strtotime($date)); // 与表结构一致：0=周日
        $schedule = DoctorSchedule::where('doctor_id', $doctorId)->where('day_of_week', $dow)->lockForUpdate()->first();
        if (! $schedule) {
            DoctorSchedule::upsert([['doctor_id' => $doctorId, 'day_of_week' => $dow, 'is_available' => true, 'time_slots' => null, 'max_patients' => 20]], ['doctor_id', 'day_of_week']);
            $schedule = DoctorSchedule::where('doctor_id', $doctorId)->where('day_of_week', $dow)->lockForUpdate()->first();
        }
        if (! $schedule) throw new BusinessException('排班信息异常', ResponseCode::BUSINESS_ERROR);
        if (! $schedule->is_available) throw new BusinessException('该医生在所选日期停诊', ResponseCode::STATUS_NOT_ALLOWED);
        $slots = $schedule->time_slots;
        if (is_array($slots) && $slots !== [] && ! in_array($time, $slots, true)) throw new BusinessException('所选时段未开放预约', ResponseCode::PARAM_OUT_OF_RANGE);
        $active = ['pending', 'called', 'in_progress', 'completed'];
        if (Appointment::where('doctor_id', $doctorId)->where('appointment_date', $date)->where('appointment_time', $time)->whereIn('status', $active)->exists()) throw new BusinessException('所选时段已被预约', ResponseCode::DUPLICATE_SUBMIT);
        if (Appointment::where('doctor_id', $doctorId)->where('appointment_date', $date)->whereIn('status', $active)->count() >= $schedule->max_patients) throw new BusinessException('该医生当日号源已满', ResponseCode::DUPLICATE_SUBMIT);
    }

    public function index(AppointmentListRequest $request): JsonResponse
    {
        $v = $request->validated(); $pid = $request->user()->id; $pp = min((int) ($v['per_page'] ?? 10), 50);
        $q = Appointment::with('doctor:id,name,title')->where('patient_id', $pid);
        if (! empty($v['status'])) $q->where('status', $v['status']);
        if (! empty($v['date'])) $q->whereDate('appointment_date', $v['date']);
        $p = $q->orderByDesc('appointment_date')->orderByDesc('appointment_time')->paginate($pp);
        return Result::success('成功', ['list' => $p->getCollection()->map(fn($a) => $this->fmt($a))->values(), 'page' => $p->currentPage(), 'size' => $p->perPage(), 'total' => $p->total(), 'total_pages' => $p->lastPage()]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $a = Appointment::with(['doctor:id,name,title,specialty,department', 'medicalRecord', 'prescription.items', 'aiDiagnosis'])->where('patient_id', $request->user()->id)->find($id);
        if (! $a) throw new BusinessException('预约记录不存在', ResponseCode::DATA_NOT_FOUND);
        return Result::success('成功', $this->fmtDetail($a));
    }

    public function cancel(int $id, Request $request): JsonResponse
    {
        $a = Appointment::where('patient_id', $request->user()->id)->find($id);
        if (! $a) throw new BusinessException('预约记录不存在', ResponseCode::DATA_NOT_FOUND);
        if (! $a->canCancel()) throw new BusinessException($a->status === 'cancelled' ? '该预约已取消' : '当前状态不可取消', ResponseCode::STATUS_NOT_ALLOWED);
        $a->update(['status' => 'cancelled']);
        return Result::success('预约已取消');
    }

    public function availableSlots(Request $request): JsonResponse
    {
        $request->validate(['doctor_id' => 'required|integer|exists:users,id,role,doctor,status,active', 'date' => 'required|date|after_or_equal:today']);
        $doctorId = (int) $request->input('doctor_id'); $date = $request->input('date');
        $dow = (int) date('w', strtotime($date));
        $schedule = DoctorSchedule::where('doctor_id', $doctorId)->where('day_of_week', $dow)->first();
        $all = (is_array($schedule?->time_slots) && $schedule->time_slots !== []) ? $schedule->time_slots : CreateAppointmentRequest::ALLOWED_TIMES;
        if ($schedule && ! $schedule->is_available) {
            $booked = $available = [];
        } else {
            $booked = Appointment::where('doctor_id', $doctorId)->where('appointment_date', $date)->whereIn('status', ['pending', 'called', 'in_progress', 'completed'])->pluck('appointment_time')->toArray();
            $available = array_values(array_diff($all, $booked));
            if ($schedule && count($booked) >= $schedule->max_patients) $available = [];
        }
        return Result::success('成功', ['date' => $date, 'all_slots' => $all, 'booked_slots' => $booked, 'available_slots' => $available]);
    }

    public function review(int $id, Request $request): JsonResponse
    {
        $request->validate(['rating' => 'required|integer|min:1|max:5', 'content' => 'nullable|string|max:500']);
        $a = Appointment::where('patient_id', $request->user()->id)->find($id);
        if (! $a) throw new BusinessException('预约记录不存在', ResponseCode::DATA_NOT_FOUND);
        if ($a->status !== 'completed') throw new BusinessException('仅可评价已完成的就诊', ResponseCode::STATUS_NOT_ALLOWED);
        return Result::success('评价提交成功', ['appointment_id' => $a->id, 'rating' => (int) $request->input('rating'), 'content' => $request->input('content')]);
    }

    private function fmt(Appointment $a): array { return ['id' => $a->id, 'appointment_date' => $a->appointment_date, 'appointment_time' => $a->appointment_time, 'status' => $a->status, 'doctor' => $a->doctor ? ['id' => $a->doctor->id, 'name' => $a->doctor->name, 'title' => $a->doctor->title, 'department' => $a->doctor->department] : null, 'created_at' => $a->created_at]; }

    private function fmtDetail(Appointment $a): array
    {
        $d = $this->fmt($a);
        $d['doctor'] = $a->doctor ? ['id' => $a->doctor->id, 'name' => $a->doctor->name, 'title' => $a->doctor->title, 'specialty' => $a->doctor->specialty, 'department' => $a->doctor->department] : null;
        $d['medical_record'] = $a->medicalRecord ? ['id' => $a->medicalRecord->id, 'symptoms' => $a->medicalRecord->symptoms, 'imaging_findings' => $a->medicalRecord->imaging_findings, 'preliminary_diagnosis' => $a->medicalRecord->preliminary_diagnosis, 'treatment_plan' => $a->medicalRecord->treatment_plan, 'created_at' => $a->medicalRecord->created_at] : null;
        $d['prescription'] = $a->prescription ? ['id' => $a->prescription->id, 'status' => $a->prescription->status, 'items' => $a->prescription->items->map(fn($i) => ['id' => $i->id, 'drug_id' => $i->drug_id, 'quantity' => $i->quantity, 'dosage' => $i->dosage, 'instructions' => $i->instructions])->values(), 'created_at' => $a->prescription->created_at] : null;
        if ($ai = $a->aiDiagnosis) $d['ai_diagnosis'] = ['id' => $ai->id, 'type' => $ai->type, 'symptom_description' => $ai->symptom_description, 'analysis' => $ai->analysis, 'risk_level' => $ai->risk_level, 'risk_warning' => $ai->risk_warning, 'advice' => $ai->advice, 'possible_conditions' => $ai->possible_conditions, 'created_at' => $ai->created_at];
        else $d['ai_diagnosis'] = null;
        return $d;
    }

    private function fmtSimple(?Appointment $a): ?array { return $a ? ['id' => $a->id, 'appointment_date' => $a->appointment_date, 'appointment_time' => $a->appointment_time, 'status' => $a->status, 'doctor_name' => $a->doctor->name ?? '', 'doctor_title' => $a->doctor->title ?? '', 'department' => $a->doctor->department ?? ''] : null; }
}
