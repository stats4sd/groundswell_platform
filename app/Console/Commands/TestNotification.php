<?php

namespace App\Console\Commands;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class TestNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test notification to all users.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {

        Notification::make('test')
            ->title('Testing')
            ->warning()
            ->body('This is a test notification.')
            ->broadcast(User::all())
            ->send();

    }
}
