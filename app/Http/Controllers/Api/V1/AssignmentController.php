<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AssignmentRequest;
use App\Models\Assignment;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    protected $assignments;
    protected $assignment;
    protected array $relations = ['unitshift', 'workers'];

    public function index()
    {
        $this->assignments = Assignment::with($this->relations)->get();
        return response()->json([
            'data' => $this->assignments
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AssignmentRequest $request)
    {
        $this->assignment = Assignment::create($request->all());
        return response()->json([
            'data' => $this->assignment
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
