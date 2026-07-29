<?php

namespace App\Http\Controllers\Api\ZZT;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ZZT\CreateAppointmentRequest;
use App\Http\Requests\ZZT\AppointmentListRequest;
use App\Models\Appointment;
use App\Models\User;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        if (Appointment::where('patient_id', $pid)->whereIn('status', ['pending', 'called', 'in_progress'])->exists()) throw new BusinessException('您已有一个进行中的预约', ResponseCode::DUPLICATE_SUBMIT);
        $a = Appointment::create(['patient_id' => $pid, 'doctor_id' => $v['doctor_id'], 'appointment_date' => $v['appointment_date'], 'appointment_time' => $v['appointment_time'], 'status' => 'pending']);
        $a->load('doctor:id,name,title,department');
        return Result::success('预约成功', $this->fmt($a));
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
        $all = ['08:30', '09:15', '10:00', '10:45', '13:30', '14:15', '15:00', '15:45'];
        $booked = Appointment::where('doctor_id', $request->input('doctor_id'))->where('appointment_date', $request->input('date'))->whereIn('status', ['pending', 'called', 'in_progress'])->pluck('appointment_time')->toArray();
        return Result::success('成功', ['date' => $request->input('date'), 'all_slots' => $all, 'booked_slots' => $booked, 'available_slots' => array_values(array_diff($all, $booked))]);
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
