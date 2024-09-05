<?php

namespace App\Http\Controllers;

use App\Models\student;
use App\Models\teacher;
use App\Models\session;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $students = student::all();

        if ($students === null)
            return response()->json('Sorry, No Students Here', 404);

        return response()->json($students);
    }

    public function getByTeacher(teacher $teacher)
    {
        $students = student::where('teacher_id', $teacher->id)->get();

        return response()->json($students);
    }

    /**
     * Display the specified resource.
     */
    public function showInfo(student $student)
    {
        $student = student::find($student->id);
        $sessions = session::where('student_id', $student->id)->get();

        // last session - DONE
        $lastSession = session::where('student_id', $student->id)
            ->latest()->first();
        // name - DONE
        $name = $student->name;

        // teacher name - DONE
        $teacher_name = $student->teacher_name;

        if (!$sessions || !$lastSession) {
            $response = [
                'name' => $name,
                'teacher_name' => $teacher_name,
                'last_session' => null,
                'remain_pages' => null,
                'remain_verses' => null,
                'average_marks' => null,
                'tested_verses' => null,
                'notes' => null
            ];
            return response()->json($response);
        } else {

            // remain amount - DONE
            $remainPages = (int)(604 - last($lastSession->pages));
            $remainVerses = (float)($remainPages / 20);

            // tested verses - DONE
            $testedVerses = $student->tested_verses;

            // sessions count in every month - DONE
            // $avgSessionsInMonth = count($sessions);

            // avarege marks - DONE
            $sum = 0;
            for ($i = 0; $i < count($sessions); $i++) {
                $sum = $sum + $sessions[$i]->mark;
            }
            $avgMarks = (float)($sum / count($sessions));

            // notes - DONE
            $notes = $student->notes;

            $response = [
                'name' => $name,
                'teacher_name' => $teacher_name,
                'last_session' => $lastSession,
                'remain_pages' => $remainPages,
                'remain_verses' => $remainVerses,
                'average_marks' => $avgMarks,
                'tested_verses' => $testedVerses,
                'notes' => $notes
            ];
            return response()->json($response);
        }
    }

    public function getstforteacherwithinfo(Teacher $teacher)
{

    $students = Student::where('teacher_id', $teacher->id)->get();
    $studentsWithInfo = [];

    foreach ($students as $student) {
        $sessions = Session::where('student_id', $student->id)->get();

        $lastSession = Session::where('student_id', $student->id)
            ->latest()->first();

    
        $name = $student->name;
        $teacher_name = $student->teacher_name;

        if (!$sessions || !$lastSession) {
            $studentInfo = [
                'id' => $student->id,
                'name' => $name,
                'teacher_name' => $teacher_name,
                'last_session' => null,
                'remain_pages' => null,
                'remain_verses' => null,
                'average_marks' => null,
                'tested_verses' => null,
                'notes' => null
            ];
        } else {
            // Calculate remaining pages and verses
            $remainPages = (int)(604 - last($lastSession->pages));
            $remainVerses = (float)($remainPages / 20);

            // Calculate average marks
            $sum = 0;
            foreach ($sessions as $session) {
                $sum += $session->mark;
            }
            $avgMarks = (float)($sum / count($sessions));

            // Student's detailed info
            $studentInfo = [
                'id' => $student->id,
                'name' => $name,
                'teacher_name' => $teacher_name,
                'last_session' => $lastSession,
                'remain_pages' => $remainPages,
                'remain_verses' => $remainVerses,
                'average_marks' => $avgMarks,
                'tested_verses' => $student->tested_verses,
                'notes' => $student->notes
            ];
        }
        // Add the detailed info to the array
        $studentsWithInfo[] = $studentInfo;
    }
    return response()->json($studentsWithInfo);
}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, student $student)
    {
        $teacher = teacher::find($request->teacher_id);

        $student->update([
            'teacher_id' => $teacher->id,
            'teacher_name' => $teacher->name,
            'phone' => $request->phone,
            'starting_date' => $request->starting_date,
            'tested_verses' => $request->tested_verses,
            'notes' => $request->notes
        ]);

        return response()->json($student);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(student $student)
    {
        $user = User::where('id', $student->user_id);
        $student->delete();
        $user->delete();
        return response()->json('Student deleted successfully', 204);
    }
}
