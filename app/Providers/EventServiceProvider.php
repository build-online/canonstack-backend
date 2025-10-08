<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\UserCreated;
use App\Events\UserLoggedIn;
use App\Events\DatasetCreated;
use App\Events\DatasetApproved;
use App\Events\DatasetCommented;
use App\Events\DatasetDownloaded;
use App\Events\ModelCreated;
use App\Events\ModelApproved;
use App\Events\ModelCommented;
use App\Events\ModelDownloaded;
use App\Listeners\SendSlackNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        
        // User events
        UserCreated::class => [
            SendSlackNotification::class,
        ],
        UserLoggedIn::class => [
            SendSlackNotification::class,
        ],
        
        // Dataset events
        DatasetCreated::class => [
            SendSlackNotification::class,
        ],
        DatasetApproved::class => [
            SendSlackNotification::class,
        ],
        DatasetCommented::class => [
            SendSlackNotification::class,
        ],
        DatasetDownloaded::class => [
            SendSlackNotification::class,
        ],
        
        // Model events
        ModelCreated::class => [
            SendSlackNotification::class,
        ],
        ModelApproved::class => [
            SendSlackNotification::class,
        ],
        ModelCommented::class => [
            SendSlackNotification::class,
        ],
        ModelDownloaded::class => [
            SendSlackNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
