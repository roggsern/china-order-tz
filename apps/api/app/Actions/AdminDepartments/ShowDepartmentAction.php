<?php

namespace App\Actions\AdminDepartments;

use App\Models\Department;

class ShowDepartmentAction
{
    public function handle(Department $department): Department
    {
        return $department;
    }
}
