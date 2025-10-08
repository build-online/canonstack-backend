<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SlackNotificationService
{
    private string $webhookUrl;
    private string $channel;
    private string $username;
    private string $iconEmoji;

    public function __construct()
    {
        $this->webhookUrl = config('services.slack.webhook_url');
        $this->channel = config('services.slack.channel');
        $this->username = config('services.slack.username');
        $this->iconEmoji = config('services.slack.icon_emoji');
    }

    /**
     * Send a notification to Slack
     */
    public function sendNotification(string $message, array $attachments = []): bool
    {
        if (empty($this->webhookUrl) || $this->webhookUrl === 'null') {
            Log::warning('Slack webhook URL not configured or is null');
            return false;
        }

        try {
            $payload = [
                'channel' => $this->channel,
                'username' => $this->username,
                'icon_emoji' => $this->iconEmoji,
                'text' => $message,
            ];

            if (!empty($attachments)) {
                $payload['attachments'] = $attachments;
            }

            $response = Http::timeout(10)->post($this->webhookUrl, $payload);

            if ($response->successful()) {
                Log::info('Slack notification sent successfully', ['message' => $message]);
                return true;
            } else {
                Log::error('Failed to send Slack notification', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'message' => $message
                ]);
                return false;
            }
        } catch (Exception $e) {
            Log::error('Exception while sending Slack notification', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);
            return false;
        }
    }

    /**
     * Send a user created notification
     */
    public function sendUserCreatedNotification(string $userName, string $email, string $username): bool
    {
        $message = "👤 *New User Registered*";
        $attachments = [
            [
                'color' => 'good',
                'fields' => [
                    [
                        'title' => 'Name',
                        'value' => $userName,
                        'short' => true
                    ],
                    [
                        'title' => 'Email',
                        'value' => $email,
                        'short' => true
                    ],
                    [
                        'title' => 'Username',
                        'value' => $username,
                        'short' => true
                    ]
                ],
                'footer' => 'CanonStack',
                'ts' => time()
            ]
        ];

        return $this->sendNotification($message, $attachments);
    }

    /**
     * Send a user logged in notification
     */
    public function sendUserLoggedInNotification(string $userName, string $timestamp): bool
    {
        $message = "🔐 *User Logged In*";
        $attachments = [
            [
                'color' => 'good',
                'fields' => [
                    [
                        'title' => 'User',
                        'value' => $userName,
                        'short' => true
                    ],
                    [
                        'title' => 'Time',
                        'value' => $timestamp,
                        'short' => true
                    ]
                ],
                'footer' => 'CanonStack',
                'ts' => time()
            ]
        ];

        return $this->sendNotification($message, $attachments);
    }

    /**
     * Send a dataset created notification
     */
    public function sendDatasetCreatedNotification(string $datasetName, string $creatorName, string $description = null): bool
    {
        $message = "📊 *New Dataset Created*";
        $fields = [
            [
                'title' => 'Dataset',
                'value' => $datasetName,
                'short' => true
            ],
            [
                'title' => 'Creator',
                'value' => $creatorName,
                'short' => true
            ]
        ];

        if ($description) {
            $fields[] = [
                'title' => 'Description',
                'value' => $description,
                'short' => false
            ];
        }

        $attachments = [
            [
                'color' => 'good',
                'fields' => $fields,
                'footer' => 'CanonStack',
                'ts' => time()
            ]
        ];

        return $this->sendNotification($message, $attachments);
    }

    /**
     * Send a dataset approved notification
     */
    public function sendDatasetApprovedNotification(string $datasetName, string $approverName): bool
    {
        $message = "✅ *Dataset Approved*";
        $attachments = [
            [
                'color' => 'good',
                'fields' => [
                    [
                        'title' => 'Dataset',
                        'value' => $datasetName,
                        'short' => true
                    ],
                    [
                        'title' => 'Approved by',
                        'value' => $approverName,
                        'short' => true
                    ]
                ],
                'footer' => 'CanonStack',
                'ts' => time()
            ]
        ];

        return $this->sendNotification($message, $attachments);
    }

    /**
     * Send a dataset commented notification
     */
    public function sendDatasetCommentedNotification(string $datasetName, string $commenterName, ?string $comment): bool
    {
        $message = "💬 *New Comment on Dataset*";
        $attachments = [
            [
                'color' => '#36a64f',
                'fields' => [
                    [
                        'title' => 'Dataset',
                        'value' => $datasetName,
                        'short' => true
                    ],
                    [
                        'title' => 'Commenter',
                        'value' => $commenterName,
                        'short' => true
                    ],
                    [
                        'title' => 'Comment',
                        'value' => $comment ?? 'No comment text available',
                        'short' => false
                    ]
                ],
                'footer' => 'CanonStack',
                'ts' => time()
            ]
        ];

        return $this->sendNotification($message, $attachments);
    }

    /**
     * Send a dataset downloaded notification
     */
    public function sendDatasetDownloadedNotification(string $datasetName, string $downloaderName): bool
    {
        $message = "📥 *Dataset Downloaded*";
        $attachments = [
            [
                'color' => '#36a64f',
                'fields' => [
                    [
                        'title' => 'Dataset',
                        'value' => $datasetName,
                        'short' => true
                    ],
                    [
                        'title' => 'Downloaded by',
                        'value' => $downloaderName,
                        'short' => true
                    ]
                ],
                'footer' => 'CanonStack',
                'ts' => time()
            ]
        ];

        return $this->sendNotification($message, $attachments);
    }

    /**
     * Send a model created notification
     */
    public function sendModelCreatedNotification(string $modelName, string $creatorName, string $description = null): bool
    {
        $message = "🤖 *New Model Created*";
        $fields = [
            [
                'title' => 'Model',
                'value' => $modelName,
                'short' => true
            ],
            [
                'title' => 'Creator',
                'value' => $creatorName,
                'short' => true
            ]
        ];

        if ($description) {
            $fields[] = [
                'title' => 'Description',
                'value' => $description,
                'short' => false
            ];
        }

        $attachments = [
            [
                'color' => 'good',
                'fields' => $fields,
                'footer' => 'CanonStack',
                'ts' => time()
            ]
        ];

        return $this->sendNotification($message, $attachments);
    }

    /**
     * Send a model approved notification
     */
    public function sendModelApprovedNotification(string $modelName, string $approverName): bool
    {
        $message = "✅ *Model Approved*";
        $attachments = [
            [
                'color' => 'good',
                'fields' => [
                    [
                        'title' => 'Model',
                        'value' => $modelName,
                        'short' => true
                    ],
                    [
                        'title' => 'Approved by',
                        'value' => $approverName,
                        'short' => true
                    ]
                ],
                'footer' => 'CanonStack',
                'ts' => time()
            ]
        ];

        return $this->sendNotification($message, $attachments);
    }

    /**
     * Send a model commented notification
     */
    public function sendModelCommentedNotification(string $modelName, string $commenterName, ?string $comment): bool
    {
        $message = "💬 *New Comment on Model*";
        $attachments = [
            [
                'color' => '#36a64f',
                'fields' => [
                    [
                        'title' => 'Model',
                        'value' => $modelName,
                        'short' => true
                    ],
                    [
                        'title' => 'Commenter',
                        'value' => $commenterName,
                        'short' => true
                    ],
                    [
                        'title' => 'Comment',
                        'value' => $comment ?? 'No comment text available',
                        'short' => false
                    ]
                ],
                'footer' => 'CanonStack',
                'ts' => time()
            ]
        ];

        return $this->sendNotification($message, $attachments);
    }

    /**
     * Send a model downloaded notification
     */
    public function sendModelDownloadedNotification(string $modelName, string $downloaderName): bool
    {
        $message = "📥 *Model Downloaded*";
        $attachments = [
            [
                'color' => '#36a64f',
                'fields' => [
                    [
                        'title' => 'Model',
                        'value' => $modelName,
                        'short' => true
                    ],
                    [
                        'title' => 'Downloaded by',
                        'value' => $downloaderName,
                        'short' => true
                    ]
                ],
                'footer' => 'CanonStack',
                'ts' => time()
            ]
        ];

        return $this->sendNotification($message, $attachments);
    }
}
