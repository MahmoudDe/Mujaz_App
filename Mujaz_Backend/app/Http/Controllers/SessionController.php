<?php

namespace App\Http\Controllers;


use Illuminate\Support\Facades\Log;
use App\Models\session;
use App\Models\student;
use App\Models\teacher;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

        
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
                        'رقم الجلسة' => (string) $session->id, 
                        'الكمية' => (string) $request->amount,
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
                        $title = 'جلسة جديدة تمت بواسطة الأستاذ ' . $teacher->name . '!🚀';
                        $body = "تم تسميع جلسة جديدة✔️ للطالب " . $student->name . "!\n";

                        $customData = [
                            'التاريخ' => $request->date,
                            'الأستاذ' => $teacher->name,
                            'رقم الجلسة' => (string) $session->id,
                            'الكمية' => (string) $request->amount,
                        ];

                        $this->sendNotification($adminDeviceToken, $title, $body, $customData);
                        Log::info('Notification sent to admin by teacher', ['deviceToken' => $adminDeviceToken]);
                    }}
                // Send notification to the teacher's device
                $teacherDeviceToken = Notification::where('user_id', $teacher->user_id)->value('device_token');
    
                if ($teacherDeviceToken) {
                    $title = ' جلسة جديدة للطالب 👑' . $student->name . '!';
                    $body = 'لقد قمت بإرسال الجلسة للأدمن 😍🫶🏻';
    
                    $customData = [
                        'التاريخ' => $request->date,
                        'الأستاذ' => $teacher->name,
                        'رقم الجلسة' => (string) $session->id, 
                        'الكمية' => (string) $request->amount, 
                    ];
                    
    
                    $this->sendNotification($teacherDeviceToken, $title, $body, $customData);
                    Log::info('Notification sent to teacher', ['deviceToken' => $teacherDeviceToken]);
                } else {
                    Log::warning('No device token found for teacher', ['teacher_id' => $teacher->id]);
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

    protected function getAccessToken()
    {
        try {
            $jsonKeyFile = '/var/www/Mujaz_App/credentials/firebase-service-account.json';
            Log::info('Reading service account JSON file', ['path' => $jsonKeyFile]);
    
            $key = json_decode(file_get_contents($jsonKeyFile), true);
    
            if (!$key) {
                Log::error('Failed to decode service account JSON');
                return null;
            }
    
            Log::info('Service account JSON decoded successfully', ['client_email' => $key['client_email']]);
    
            $client = new \Firebase\JWT\JWT();
            $jwtClient = new \Firebase\JWT\JWT();
            $token = $jwtClient->encode([
                'iss' => $key['client_email'],
                'sub' => $key['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => time() + 3600,
                'iat' => time()
            ], $key['private_key'], 'RS256');
    
            Log::info('JWT token generated successfully');
    
            $client = new \GuzzleHttp\Client();
            $response = $client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $token,
                ],
            ]);
    
            Log::info('Token request sent to Google OAuth');
    
            $accessToken = json_decode((string)$response->getBody(), true);
    
            if (isset($accessToken['access_token'])) {
                Log::info('Access token retrieved successfully', ['access_token' => $accessToken['access_token']]);
                return $accessToken['access_token'];
            } else {
                Log::error('Access token not found in response', ['response' => $accessToken]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error fetching access token', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    
    protected function sendNotification($deviceToken, $title, $body, $customData)
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return response()->json('Failed to obtain access token', 500);
            }
    
            // FCM API endpoint
            $url = 'https://fcm.googleapis.com/v1/projects/mujaz-notifications/messages:send';

            $data = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $customData,
                ],
            ];
      
            $client = new \GuzzleHttp\Client();
            $response = $client->post($url, [
                'json' => $data,
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                Log::info('FCM notification sent successfully', ['response' => (string)$response->getBody()]);
                return response()->json('Notification sent successfully', 200);
            } else {
                Log::error('Failed to send FCM notification', ['response' => (string)$response->getBody()]);
                return response()->json('Failed to send notification', 500);
            }
        } catch (\Exception $e) {
            Log::error('Error sending FCM notification', ['error' => $e->getMessage()]);
            return response()->json('Failed to send notification due to error', 500);
        }
    }
    
    

    // protected function sendNotification($deviceToken, $title, $body, $customData)
    // {
    //     $url = 'https://fcm.googleapis.com/fcm/send';
    //     $serverKey = 'AAAAn5DuF_g:APA91bExwuB_tW3W_OaV1DhJpLXIxqmh1XVBW4tP4-N-Yt0zS6Q7l5QuvQ_6ZDtdEgjeUyILouiMSMBn8VhMrfhJ0kzsJ5l65kYLjG0iPRG-zS-VxjO7LkfW9ktd-X3_gtind_zvZJ0a';

    //     $data = [
    //         'to' => $deviceToken,
    //         'notification' => [
    //             'title' => $title,
    //             'body' => $body,
    //             'icon' => 'ic_notification',
    //             'sound' => 'default',
    //         ],
    //         'data' => $customData,
    //     ];

    //     $response = Http::withHeaders([
    //         'Authorization' => 'key=' . $serverKey,
    //         'Content-Type' => 'application/json',
    //     ])->post($url, $data);

    //     if ($response->successful()) {
    //         Log::info('FCM notification sent successfully', ['response' => $response->body()]);
    //         return response()->json('Notification sent successfully', 200);
    //     } else {
    //         Log::error('Failed to send FCM notification', ['response' => $response->body()]);
    //         return response()->json('Failed to send notification', 500);
    //     }
    // }


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
