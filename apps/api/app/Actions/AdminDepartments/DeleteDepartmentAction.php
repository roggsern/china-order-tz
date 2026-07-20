<?php

namespace App\Actions\AdminDepartments;

use App\Models\Department;

class DeleteDepartmentAction
{
    public function handle(Department $department): void
    {
        $department->delete();
    }
}
