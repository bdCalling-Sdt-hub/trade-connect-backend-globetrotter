<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function activeUser()
    {
        $activeUsersCount = User::where('status', 'active')->count();

        if ($activeUsersCount === 0) {
            return $this->sendError('No active users found!', [], 404);
        }

        return $this->sendResponse($activeUsersCount, 'Successfully retrieved active users.');
    }
}
