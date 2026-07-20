<?php

namespace App\Actions\AdminDepartments;

use App\Models\Department;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RestoreDepartmentAction
{
    public function handle(string $id): Department
    {
        $department = Department::onlyTrashed()->whereKey($id)->first();

        if ($department === null) {
            throw (new ModelNotFoundException)->setModel(Department::class, [$id]);
        }

        $department->restore();

        return $department->fresh();
    }
}
