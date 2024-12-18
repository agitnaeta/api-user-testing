<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserInputRequest;
use App\Http\Requests\UserSearchRequest;
use App\Mail\NewUserRegister;
use App\Mail\UserConfirmRegister;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(UserSearchRequest $request)
    {
        try {
            // Allowed sorting fields
            $allowedSortBy = collect(['name', 'email', 'created_at']);

            // Build the base query
            $query = User::isActive()->withCount('orders')
                         ->when($request->search, function ($q) use ($request) {
                             $q->where(function ($subQuery) use ($request) {
                                 $subQuery->where('name', 'like', "%{$request->search}%")
                                          ->orWhere('email', 'like', "%{$request->search}%");
                             });
                         });

            // Apply sorting
            $sortBy = $request->sortBy && $allowedSortBy->contains($request->sortBy) ? $request->sortBy : 'created_at';
            $query->orderBy($sortBy);

            // Paginate the results
            $usersPaginated = $query->paginate(10);

            // Return enhanced response
            return response()->json([
                "page" => $usersPaginated->currentPage(),
                "users" => $usersPaginated->items(),
            ], 200);

        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                "error" => "Failed to retrieve users.",
                "details" => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserInputRequest $request)
    {
        try {
            // Create user
            $user = User::create($request->validated());

            // Send notification emails
            $this->sendingNotificationEmail($user);

            return response()->json($user,201);

        } catch (\Throwable $e) {
            // Log the exception
            Log::error('Error creating user: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            // Return error response
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while creating the user.',
            ], 500);
        }
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


    /**
     * Sending email using queue, so it's not blocking API request
     * @param User $user
     * @return void
     */
    private function sendingNotificationEmail(User $user)
    {
        // Sending to user
        Mail::to($user->email)->queue(new UserConfirmRegister($user));

        // Sending to administrator, let's say here we have multiple administrators
        $administratorEmails = User::isAdministrator()->pluck('email');
        foreach($administratorEmails as $administratorEmail){
            Mail::to($administratorEmail)->queue(new NewUserRegister($user));
        }
    }
}
