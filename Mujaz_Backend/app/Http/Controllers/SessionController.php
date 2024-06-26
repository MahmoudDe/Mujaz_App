<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;

use App\Models\session;
use App\Models\student;
use App\Models\teacher;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sessions = session::all();
        return response()->json($sessions);
    }



    public function store(Request $request)
    {
        Log::info('Starting session creation', $request->all());

        $user = User::find($request->user_id);
        $student = Student::find($request->student_id);
        $teacher = Teacher::where('user_id', $request->user_id)->first();

        if (!$user || !$student) {
            Log::error('User or student not found', ['user_id' => $request->user_id, 'student_id' => $request->student_id]);
            return response()->json(['error' => 'User or student not found'], 404);
        }

        try {
            if ($user->role === 'admin') {
                $session = Session::create([
                    'date' => $request->date,
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'teacher_id' => $user->id,
                    'teacher_name' => $user->name,
                    'surah' => $request->surah,
                    'pages' => $request->pages,
                    'ayat' => $request->ayat,
                    'amount' => $request->amount,
                    'mistakes' => $request->mistakes,
                    'taps_num' => $request->taps_num,
                    'mark' => $request->mark,
                    'duration' => $request->duration,
                    'notes' => $request->notes,
                ]);

                Log::info('Session created by admin', ['session' => $session]);

                $adminDeviceToken = Notification::where('user_id', $user->id)->value('device_token');

                if ($adminDeviceToken) {
                    $title = 'جلسة جديدة للطالب ' . $student->name . '!';
                    $body = "تم تسميع جلسة جديدة 😍! قم بإنشاء موعد آخر";

                    $customData = [
                        'التاريخ' => $request->date,
                        'الأستاذ' => $user->name,
                        'رقم الجلسة' => $session->id,
                        'الكمية' => $request->amount,
                    ];

                    $this->sendNotification($adminDeviceToken, $title, $body, $customData);
                    Log::info('Notification sent to admin', ['deviceToken' => $adminDeviceToken]);
                }
            } else if ($user->role === 'teacher') {
                if (!$teacher) {
                    Log::error('Teacher not found for user', ['user_id' => $request->user_id]);
                    return response()->json(['error' => 'Teacher not found'], 404);
                }

                $session = Session::create([
                    'date' => $request->date,
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->name,
                    'surah' => $request->surah,
                    'pages' => $request->pages,
                    'ayat' => $request->ayat,
                    'amount' => $request->amount,
                    'mistakes' => $request->mistakes,
                    'taps_num' => $request->taps_num,
                    'mark' => $request->mark,
                    'duration' => $request->duration,
                    'notes' => $request->notes,
                ]);

                Log::info('Session created by teacher', ['session' => $session]);

                $admin = User::where('role', 'admin')->first();
                if ($admin) {
                    $adminDeviceToken = Notification::where('user_id', $admin->id)->value('device_token');

                    if ($adminDeviceToken) {
                        $title = 'جلسة جديدة تمت بواسطة الأستاذ ' . $teacher->name . '!😍';
                        $body = "تم تسميع جلسة جديدة✔️ للطالب " . $student->name . "!\n";

                        $customData = [
                            'التاريخ' => $request->date,
                            'الأستاذ' => $teacher->name,
                            'رقم الجلسة' => $session->id,
                            'الكمية' => $request->amount,
                        ];

                        $this->sendNotification($adminDeviceToken, $title, $body, $customData);
                        Log::info('Notification sent to admin by teacher', ['deviceToken' => $adminDeviceToken]);
                    }
                }
            }

            return response()->json('Session created successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error creating session', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }


    protected function sendNotification($deviceToken, $title, $body, $customData)
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $serverKey = 'AAAAn5DuF_g:APA91bExwuB_tW3W_OaV1DhJpLXIxqmh1XVBW4tP4-N-Yt0zS6Q7l5QuvQ_6ZDtdEgjeUyILouiMSMBn8VhMrfhJ0kzsJ5l65kYLjG0iPRG-zS-VxjO7LkfW9ktd-X3_gtind_zvZJ0a';

        $data = [
            'to' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => 'ic_notification',
                'sound' => 'default',
            ],
            'data' => $customData,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type' => 'application/json',
        ])->post($url, $data);

        if ($response->successful()) {
            Log::info('FCM notification sent successfully', ['response' => $response->body()]);
            return response()->json('Notification sent successfully', 200);
        } else {
            Log::error('Failed to send FCM notification', ['response' => $response->body()]);
            return response()->json('Failed to send notification', 500);
        }
    }






    // Get sessions by studnet
    public function getByStudent(student $student)
    {
        $student_id = $student->id;

        $sessions = session::where('student_id', $student_id)->get();
        return response()->json($sessions, 200);
    }

    // Get sessions by teacher
    public function getByTeacher(teacher $teacher)
    {
        $teacher_id = $teacher->id;

        $sessions = session::where('teacher_id', $teacher_id)->get();
        return response()->json($sessions, 200);
    }

    public function filteredSessions(Request $request)
    {

        $student_name = $request->query('student_name');
        $teacher_name = $request->query('teacher_name');
        $dateFrom = $request->query('dateFrom');
        $dateTo = $request->query('dateTo');


        $sessions = session::where(function ($query) use ($teacher_name, $student_name, $dateFrom, $dateTo) {

            if ($student_name && $teacher_name && $dateFrom && $dateTo) {
                $query->where('teacher_name', $teacher_name)
                    ->where('student_name', $student_name)
                    ->whereBetween('date', [$dateFrom, $dateTo]);
            }

            if ($teacher_name && $dateFrom && $dateTo) {
                $query->where('teacher_name', $teacher_name)
                    ->whereBetween('date', [$dateFrom, $dateTo]);
            }
            if ($student_name && $dateFrom && $dateTo) {
                $query->where('student_name', $student_name)
                    ->whereBetween('date', [$dateFrom, $dateTo]);
            }
            if ($teacher_name) {
                $query->where('teacher_name', $teacher_name);
            }
            if ($student_name) {
                $query->where('student_name', $student_name);
            }
            if ($dateFrom && $dateTo) {
                $query->whereBetween('date', [$dateFrom, $dateTo]);
            }
        })->get();

        return response()->json($sessions, 200);
    }
    /**
     * Display the specified resource.
     */
    public function show(session $session)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, session $session)
    {
        $user = user::find($request->user_id);
        $student = student::find($request->student_id);
        $teacher = teacher::where('user_id', $request->user_id)->first();

        if ($user->role === 'admin') {
            $session->update(
                [
                    'date' => $request->date,
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'teacher_id' => $user->id,
                    'teacher_name' => $user->name,
                    'surah' => $request->surah,
                    'pages' => $request->pages,
                    'ayat' => $request->ayat,
                    'amount' => $request->amount,
                    'mistakes' => $request->mistakes,
                    'taps_num' => $request->taps_num,
                    'mark' => $request->mark,
                    'duration' => $request->duration,
                    'notes' => $request->notes
                ]
            );
        } else if ($user->role === 'teacher') {
            $session->update(
                [
                    'date' => $request->date,
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->name,
                    'surah' => $request->surah,
                    'pages' => $request->pages,
                    'ayat' => $request->ayat,
                    'amount' => $request->amount,
                    'mistakes' => $request->mistakes,
                    'taps_num' => $request->taps_num,
                    'mark' => $request->mark,
                    'duration' => $request->duration,
                    'notes' => $request->notes
                ]
            );
        }

        return response()->json($session);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(session $session)
    {
        $session->delete();
        return response()->json('session deleted succefully !!', 204);
    }
}
