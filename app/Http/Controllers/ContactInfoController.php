<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class ContactInfoController extends Controller
{
    /**
     * Get contact information for the HeaderBar component
     * Returns phone and email from the first admin user or default values
     */
    public function getContactInfo()
    {
        try {
            // Get the first user with contact information
            $userWithContact = User::whereNotNull('phone_number')
                ->whereNotNull('email')
                ->first();

            if ($userWithContact) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'phone' => $userWithContact->phone_number,
                        'email' => $userWithContact->email,
                    ]
                ]);
            }

            // Fallback: get any user with email
            $fallbackUser = User::whereNotNull('email')->first();

            if ($fallbackUser) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'phone' => $fallbackUser->phone_number ?? '+57 3214567890',
                        'email' => $fallbackUser->email,
                    ]
                ]);
            }

            // Default values if no users found
            return response()->json([
                'success' => true,
                'data' => [
                    'phone' => '+57 3214567890',
                    'email' => 'correo@gmail.com',
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'phone' => '+57 3214567890',
                    'email' => 'correo@gmail.com',
                ]
            ]);
        }
    }
}