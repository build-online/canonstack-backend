<?php

namespace App\Listeners;

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
use App\Services\SlackNotificationService;
use Illuminate\Support\Facades\Log;
use Exception;

class SendSlackNotification
{

    private SlackNotificationService $slackService;

    /**
     * Create the event listener.
     */
    public function __construct(SlackNotificationService $slackService)
    {
        $this->slackService = $slackService;
    }

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        try {
            Log::info('Slack notification listener triggered', ['event' => get_class($event)]);
            
            switch (true) {
                case $event instanceof UserCreated:
                    $this->handleUserCreated($event);
                    break;
                case $event instanceof UserLoggedIn:
                    $this->handleUserLoggedIn($event);
                    break;
                case $event instanceof DatasetCreated:
                    $this->handleDatasetCreated($event);
                    break;
                case $event instanceof DatasetApproved:
                    $this->handleDatasetApproved($event);
                    break;
                case $event instanceof DatasetCommented:
                    $this->handleDatasetCommented($event);
                    break;
                case $event instanceof DatasetDownloaded:
                    $this->handleDatasetDownloaded($event);
                    break;
                case $event instanceof ModelCreated:
                    $this->handleModelCreated($event);
                    break;
                case $event instanceof ModelApproved:
                    $this->handleModelApproved($event);
                    break;
                case $event instanceof ModelCommented:
                    $this->handleModelCommented($event);
                    break;
                case $event instanceof ModelDownloaded:
                    $this->handleModelDownloaded($event);
                    break;
                default:
                    Log::warning('Unknown event type for Slack notification', ['event' => get_class($event)]);
            }
        } catch (Exception $e) {
            Log::error('Failed to send Slack notification', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function handleUserCreated(UserCreated $event): void
    {
        $this->slackService->sendUserCreatedNotification(
            $event->user->name,
            $event->user->email,
            $event->user->username
        );
    }

    private function handleUserLoggedIn(UserLoggedIn $event): void
    {
        $this->slackService->sendUserLoggedInNotification(
            $event->user->name,
            now()->format('Y-m-d H:i:s')
        );
    }

    private function handleDatasetCreated(DatasetCreated $event): void
    {
        $this->slackService->sendDatasetCreatedNotification(
            $event->dataset->repository->name,
            $event->dataset->repository->user->name,
            $event->dataset->repository->description
        );
    }

    private function handleDatasetApproved(DatasetApproved $event): void
    {
        $this->slackService->sendDatasetApprovedNotification(
            $event->dataset->repository->name,
            $event->approver->name
        );
    }

    private function handleDatasetCommented(DatasetCommented $event): void
    {
        $this->slackService->sendDatasetCommentedNotification(
            $event->dataset->repository->name,
            $event->comment->user->name,
            $event->comment->text
        );
    }

    private function handleDatasetDownloaded(DatasetDownloaded $event): void
    {
        $this->slackService->sendDatasetDownloadedNotification(
            $event->dataset->repository->name,
            $event->downloader->name
        );
    }

    private function handleModelCreated(ModelCreated $event): void
    {
        $this->slackService->sendModelCreatedNotification(
            $event->model->repository->name,
            $event->model->repository->user->name,
            $event->model->repository->description
        );
    }

    private function handleModelApproved(ModelApproved $event): void
    {
        $this->slackService->sendModelApprovedNotification(
            $event->model->repository->name,
            $event->approver->name
        );
    }

    private function handleModelCommented(ModelCommented $event): void
    {
        $this->slackService->sendModelCommentedNotification(
            $event->model->repository->name,
            $event->comment->user->name,
            $event->comment->text
        );
    }

    private function handleModelDownloaded(ModelDownloaded $event): void
    {
        $this->slackService->sendModelDownloadedNotification(
            $event->model->repository->name,
            $event->downloader->name
        );
    }
}
