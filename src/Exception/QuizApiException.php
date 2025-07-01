<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Base exception for Quiz API
 */
abstract class QuizApiException extends HttpException
{
    protected string $errorType;

    public function __construct(
        string $message = '',
        int $statusCode = 500,
        \Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }
}

/**
 * Exception for quiz not found
 */
class QuizNotFoundException extends QuizApiException
{
    protected string $errorType = 'QUIZ_NOT_FOUND';

    public function __construct(int $quizId, \Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Quiz with ID %d not found', $quizId),
            404,
            $previous
        );
    }
}

/**
 * Exception for question not found
 */
class QuestionNotFoundException extends QuizApiException
{
    protected string $errorType = 'QUESTION_NOT_FOUND';

    public function __construct(int $questionId, \Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Question with ID %d not found', $questionId),
            404,
            $previous
        );
    }
}

/**
 * Exception for invalid quiz state
 */
class InvalidQuizStateException extends QuizApiException
{
    protected string $errorType = 'INVALID_QUIZ_STATE';

    public function __construct(string $currentState, string $expectedState, \Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Quiz is in state "%s" but expected "%s"', $currentState, $expectedState),
            400,
            $previous
        );
    }
}

/**
 * Exception for quiz validation errors
 */
class QuizValidationException extends QuizApiException
{
    protected string $errorType = 'QUIZ_VALIDATION_ERROR';
    private array $validationErrors;

    public function __construct(array $validationErrors, \Throwable $previous = null)
    {
        $this->validationErrors = $validationErrors;

        parent::__construct(
            'Quiz validation failed',
            422,
            $previous
        );
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}

/**
 * Exception for insufficient permissions
 */
class InsufficientPermissionsException extends QuizApiException
{
    protected string $errorType = 'INSUFFICIENT_PERMISSIONS';

    public function __construct(string $resource, string $action, \Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Insufficient permissions to %s %s', $action, $resource),
            403,
            $previous
        );
    }
}

/**
 * Exception for rate limiting
 */
class RateLimitExceededException extends QuizApiException
{
    protected string $errorType = 'RATE_LIMIT_EXCEEDED';

    public function __construct(int $retryAfter = 60, \Throwable $previous = null)
    {
        parent::__construct(
            'Rate limit exceeded. Please try again later.',
            429,
            $previous,
            ['Retry-After' => $retryAfter]
        );
    }
}

/**
 * Exception for business logic violations
 */
class BusinessLogicException extends QuizApiException
{
    protected string $errorType = 'BUSINESS_LOGIC_ERROR';

    public function __construct(string $message, \Throwable $previous = null)
    {
        parent::__construct($message, 400, $previous);
    }
}
