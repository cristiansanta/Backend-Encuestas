<?php

namespace App\Http\Controllers;

use App\Models\Condition;
use App\Models\ConditionsModel;
use Illuminate\Http\Request;

class ConditionsController extends Controller
{
    // Display a listing of the conditions
    public function index()
    {
        $conditions = ConditionsModel::all();
        return response()->json($conditions);
    }

    // Store a newly created condition in the database
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'id_question_child' => 'required|integer',
            'operation' => 'required|string',
            'compare' => 'required|string',
            'cod_father' => 'required|integer',
            'id_survey' => 'required|integer',
        ]);

        $condition = ConditionsModel::create($validatedData);

        return response()->json($condition, 201);
    }

    // Display the specified condition
    public function show($id)
    {
        $condition = ConditionsModel::find($id);

        if (!$condition) {
            return response()->json(['message' => 'Condition not found'], 404);
        }

        return response()->json($condition);
    }

    // Update the specified condition in the database
    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'id_question_child' => 'required|integer',
            'operation' => 'required|string',
            'compare' => 'required|string',
            'cod_father' => 'required|integer',
            'id_survey' => 'required|integer',
        ]);

        $condition = ConditionsModel::find($id);

        if (!$condition) {
            return response()->json(['message' => 'Condition not found'], 404);
        }

        $condition->update($validatedData);

        return response()->json($condition);
    }

    // Remove the specified condition from the database
    public function destroy($id)
    {
        $condition = ConditionsModel::find($id);

        if (!$condition) {
            return response()->json(['message' => 'Condition not found'], 404);
        }

        $condition->delete();

        return response()->json(['message' => 'Condition deleted']);
    }
}
