<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduleEntry;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UcasApiController extends Controller
{
    public function loginAndSync(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $loginResponse = Http::asForm()->post('https://quiztoxml.ucas.edu.ps/api/login', [
            'username' => $request->username,
            'password' => $request->password,
        ]);

        $loginBody = trim($loginResponse->body());

        if ($loginBody === 'كلمة المرور او اسم المستخدم خطا') {
            return response('كلمة المرور او اسم المستخدم خطا', 401);
        }

        if (!$loginResponse->successful()) {
            return response()->json([
                'message' => 'فشل تسجيل الدخول',
            ], $loginResponse->status());
        }

        $loginData = $loginResponse->json();

        $studentNumber = data_get($loginData, 'data.user_id');
        $studentName = data_get($loginData, 'data.user_ar_name');
        $token = data_get($loginData, 'Token');

        $student = Student::updateOrCreate(
            ['student_number' => $studentNumber],
            [
                'student_name' => $studentName,
                'token' => $token,
            ]
        );

        $tableResponse = Http::asForm()->post('https://quiztoxml.ucas.edu.ps/api/get-table', [
            'user_id' => $studentNumber,
            'token' => $token,
        ]);

        if (!$tableResponse->successful()) {
            return response()->json([
                'message' => 'فشل جلب الجدول الدراسي',
            ], $tableResponse->status());
        }

        $tableData = $tableResponse->json();
        $rows = $tableData['data'] ?? $tableData;

        $student->scheduleEntries()->delete();

        foreach ($rows as $row) {
            ScheduleEntry::create([
                'student_id' => $student->id,
                'subject_name' => $row['subject_name'] ?? null,
                'raw_row' => $row,
            ]);
        }

        return response()->json([
            'message' => 'تم تسجيل الدخول وجلب الجدول وتخزينه بنجاح',
            'student' => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'student_name' => $student->student_name,
                'token' => $student->token,
            ],
            'saved_schedule_rows' => count($rows),
        ]);
    }

    public function showStudentTable(Student $student)
    {
        return response()->json([
            'student' => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'student_name' => $student->student_name,
            ],
            'schedule' => $student->scheduleEntries()
                ->select('id', 'student_id', 'subject_name', 'raw_row')
                ->get(),
        ]);
    }
}