<?php

declare(strict_types=1);

namespace App\Domain\Lab\Policies;

use App\Domain\Lab\Models\LabOrder;
use App\Models\User;

class LabOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LabOrder $order): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('lab.bill.create');
    }

    public function discount(User $user): bool
    {
        return $user->hasPermissionTo('lab.bill.discount');
    }

    public function cancel(User $user, LabOrder $order): bool
    {
        return $user->hasPermissionTo('lab.bill.cancel');
    }

    public function collectSpecimen(User $user): bool
    {
        return $user->hasPermissionTo('lab.specimen.collect');
    }

    public function enterResults(User $user): bool
    {
        return $user->hasPermissionTo('lab.result.enter');
    }

    public function validateResults(User $user): bool
    {
        return $user->hasPermissionTo('lab.result.validate');
    }

    public function verifyResults(User $user): bool
    {
        return $user->hasPermissionTo('lab.result.verify');
    }

    public function amendReport(User $user): bool
    {
        return $user->hasPermissionTo('lab.report.amend');
    }

    public function manageMasters(User $user): bool
    {
        return $user->hasPermissionTo('lab.master.manage');
    }
}
