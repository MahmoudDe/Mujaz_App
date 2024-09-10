<?php

namespace App\Http\Controllers;

use App\Models\teacher;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $teachers = teacher::all();
        return response()->json($teachers);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
    }

    /**
     * Display the specified resource.
     */
    public function show(teacher $teacher)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(teacher $teacher)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, teacher $teacher)
    {
        $teacher->update($request->all());

        return response()->json($teacher);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(teacher $teacher)
    {
        //
    }
    public function teachersWithStudents()
    {
        $teachers = Teacher::with('students')->get();
        
        $response = $teachers->map(function($teacher) {
            return [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'phone' => $teacher->phone,
                'students' => $teacher->students->map(function($student) {
                    return [
                        'id' => $student->id,
                        'name' => $student->name,
                        'phone' => $student->phone,
                        'starting_date' => $student->starting_date,
                        'remain_pages' => $student->remain_pages,
                        'remain_verses' => $student->remain_verses,
                        'average_marks' => $student->average_marks,
                    ];
                })
            ];
        });

        return response()->json($response);
    }
}
