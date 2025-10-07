# Slack Notifications Integration

This document describes the Slack notifications system implemented in the CanonStack backend.

## Configuration

Add the following environment variables to your `.env` file:

```env
# Slack Notifications
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK
SLACK_CHANNEL=#general
SLACK_BOT_USERNAME=CanonStack Bot
SLACK_BOT_ICON=:robot_face:
```

## Events Tracked

The system sends Slack notifications for the following events:

### User Events
- **User Created**: When a new user registers
- **User Logged In**: When a user logs in

### Dataset Events
- **Dataset Created**: When a new dataset is uploaded
- **Dataset Approved**: When a dataset is approved by an admin
- **Dataset Commented**: When someone comments on a dataset
- **Dataset Downloaded**: When someone downloads a dataset

### Model Events
- **Model Created**: When a new model is uploaded
- **Model Approved**: When a model is approved by an admin
- **Model Commented**: When someone comments on a model
- **Model Downloaded**: When someone downloads a model

## Implementation Details

### Events
All events are located in `app/Events/` and follow Laravel's event system.

### Listeners
The `SendSlackNotification` listener in `app/Listeners/` handles all Slack notifications.

### Service
The `SlackNotificationService` in `app/Services/` manages the actual Slack API communication.

### Configuration
Slack settings are configured in `config/services.php` under the `slack` key.

## Setup Instructions

1. Create a Slack webhook URL in your Slack workspace
2. Add the environment variables to your `.env` file
3. The system will automatically start sending notifications when events occur

## Error Handling

- Failed Slack notifications are logged to the Laravel log
- The system continues to function even if Slack notifications fail
- All notifications are queued for background processing
