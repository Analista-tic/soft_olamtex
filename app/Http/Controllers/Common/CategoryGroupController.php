<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\Common\CategoryGroup;
use Illuminate\Http\Request;

class CategoryGroupController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:category_groups,name',
        ]);

        $group = CategoryGroup::create($request->only('name'));

        return response()->json($group, 201);
    }

    public function getData()
    {
        $groups = CategoryGroup::all();
        return response()->json($groups);
    }
}
