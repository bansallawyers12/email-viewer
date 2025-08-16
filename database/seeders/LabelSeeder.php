<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Label;
use App\Models\User;

class LabelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users
        $users = User::all();
        
        foreach ($users as $user) {
            // Create system labels for each user
            $systemLabels = [
                [
                    'name' => 'Inbox',
                    'color' => '#3B82F6', // Blue
                    'type' => 'system',
                    'icon' => 'fas fa-inbox',
                    'description' => 'Default inbox for incoming emails',
                ],
                [
                    'name' => 'Sent',
                    'color' => '#10B981', // Green
                    'type' => 'system',
                    'icon' => 'fas fa-paper-plane',
                    'description' => 'Emails sent from your domain',
                ],
            ];
            
            foreach ($systemLabels as $labelData) {
                Label::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'name' => $labelData['name'],
                    ],
                    [
                        'color' => $labelData['color'],
                        'type' => $labelData['type'],
                        'icon' => $labelData['icon'],
                        'description' => $labelData['description'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
