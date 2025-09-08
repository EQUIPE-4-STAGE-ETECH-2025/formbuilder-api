<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

class QuotaExceededException extends \RuntimeException
{
    private const QUOTA_ERROR_CODES = [
        'create_form' => 'QUOTA_FORMS_EXCEEDED',
        'submit_form' => 'QUOTA_SUBMISSIONS_EXCEEDED',
        'upload_file' => 'QUOTA_STORAGE_EXCEEDED',
    ];

    private string $actionType;
    private string $errorCode;
    private int $currentUsage;
    private int $maxLimit;

    public function __construct(
        string $actionType,
        string $message,
        int $currentUsage,
        int $maxLimit,
        ?\Throwable $previous = null
    ) {
        $errorCode = self::QUOTA_ERROR_CODES[$actionType] ?? 'QUOTA_EXCEEDED';

        parent::__construct($message, 0, $previous);

        $this->actionType = $actionType;
        $this->errorCode = $errorCode;
        $this->currentUsage = $currentUsage;
        $this->maxLimit = $maxLimit;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getCurrentUsage(): int
    {
        return $this->currentUsage;
    }

    public function getMaxLimit(): int
    {
        return $this->maxLimit;
    }

    public function getHttpStatusCode(): int
    {
        return Response::HTTP_TOO_MANY_REQUESTS;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'error' => 'Quota dépassé',
            'error_code' => $this->errorCode,
            'action_type' => $this->actionType,
            'message' => $this->getMessage(),
            'current_usage' => $this->currentUsage,
            'max_limit' => $this->maxLimit,
            'percentage_used' => round(($this->currentUsage / $this->maxLimit) * 100, 2),
        ];
    }
}
