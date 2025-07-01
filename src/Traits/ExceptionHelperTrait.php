<?php

namespace App\Traits;

use App\Exception\BusinessLogicException;
use App\Exception\InsufficientPermissionsException;
use App\Exception\InvalidQuizStateException;
use App\Exception\QuestionNotFoundException;
use App\Exception\QuizNotFoundException;
use App\Exception\QuizValidationException;
use App\Exception\RateLimitExceededException;

/**
 * Trait to help throw custom API exceptions from controllers
 */
trait ExceptionHelperTrait
{
    /**
     * Throw quiz not found exception
     */
    protected function throwQuizNotFound(int $quizId): never
    {
        throw new QuizNotFoundException($quizId);
    }

    /**
     * Throw question not found exception
     */
    protected function throwQuestionNotFound(int $questionId): never
    {
        throw new QuestionNotFoundException($questionId);
    }

    /**
     * Throw invalid quiz state exception
     */
    protected function throwInvalidQuizState(string $currentState, string $expectedState): never
    {
        throw new InvalidQuizStateException($currentState, $expectedState);
    }

    /**
     * Throw quiz validation exception
     */
    protected function throwQuizValidation(array $validationErrors): never
    {
        throw new QuizValidationException($validationErrors);
    }

    /**
     * Throw insufficient permissions exception
     */
    protected function throwInsufficientPermissions(string $resource, string $action): never
    {
        throw new InsufficientPermissionsException($resource, $action);
    }

    /**
     * Throw rate limit exceeded exception
     */
    protected function throwRateLimitExceeded(int $retryAfter = 60): never
    {
        throw new RateLimitExceededException($retryAfter);
    }

    /**
     * Throw business logic exception
     */
    protected function throwBusinessLogic(string $message): never
    {
        throw new BusinessLogicException($message);
    }

    /**
     * Assert entity exists or throw not found exception
     */
    protected function assertQuizExists($quiz, int $quizId): void
    {
        if (!$quiz) {
            $this->throwQuizNotFound($quizId);
        }
    }

    /**
     * Assert question exists or throw not found exception
     */
    protected function assertQuestionExists($question, int $questionId): void
    {
        if (!$question) {
            $this->throwQuestionNotFound($questionId);
        }
    }

    /**
     * Assert user has permission or throw exception
     */
    protected function assertUserPermission(bool $hasPermission, string $resource, string $action): void
    {
        if (!$hasPermission) {
            $this->throwInsufficientPermissions($resource, $action);
        }
    }

    /**
     * Assert business rule or throw exception
     */
    protected function assertBusinessRule(bool $isValid, string $message): void
    {
        if (!$isValid) {
            $this->throwBusinessLogic($message);
        }
    }
}
