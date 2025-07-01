<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use App\Exception\QuizApiException;
use App\Exception\QuizValidationException;

/**
 * Global exception handler for API responses
 */
class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private string $environment = 'prod'
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Log the exception
        $this->logException($exception, $request);

        // Create appropriate response
        $response = $this->createJsonResponse($exception, $request);
        $event->setResponse($response);
    }

    private function createJsonResponse(\Throwable $exception, Request $request): JsonResponse
    {
        $statusCode = $this->getStatusCode($exception);
        $errorData = $this->getErrorData($exception, $statusCode);

        // Add request context in debug mode
        if ($this->environment === 'dev') {
            $errorData['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->environment === 'dev' ? $exception->getTraceAsString() : null,
                'request_uri' => $request->getRequestUri(),
                'method' => $request->getMethod(),
            ];
        }

        return new JsonResponse($errorData, $statusCode);
    }

    private function getStatusCode(\Throwable $exception): int
    {
        return match (true) {
            $exception instanceof HttpException => $exception->getStatusCode(),
            $exception instanceof NotFoundHttpException => Response::HTTP_NOT_FOUND,
            $exception instanceof AccessDeniedHttpException,
            $exception instanceof AccessDeniedException => Response::HTTP_FORBIDDEN,
            $exception instanceof UnauthorizedHttpException,
            $exception instanceof AuthenticationException => Response::HTTP_UNAUTHORIZED,
            $exception instanceof BadRequestHttpException => Response::HTTP_BAD_REQUEST,
            $exception instanceof MethodNotAllowedHttpException => Response::HTTP_METHOD_NOT_ALLOWED,
            $exception instanceof ValidationFailedException => Response::HTTP_UNPROCESSABLE_ENTITY,
            $exception instanceof ConstraintViolationException => Response::HTTP_CONFLICT,
            $exception instanceof ConnectionException => Response::HTTP_SERVICE_UNAVAILABLE,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    private function getErrorData(\Throwable $exception, int $statusCode): array
    {
        $message = $this->getErrorMessage($exception);
        $type = $this->getErrorType($exception);

        $errorData = [
            'error' => [
                'type' => $type,
                'message' => $message,
                'code' => $statusCode,
                'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
            ]
        ];

        // Add specific error details based on exception type
        $this->addSpecificErrorDetails($errorData, $exception);

        return $errorData;
    }

    private function getErrorMessage(\Throwable $exception): string
    {
        // In production, return generic messages for security
        if ($this->environment === 'prod') {
            return match (true) {
                $exception instanceof NotFoundHttpException => 'Resource not found',
                $exception instanceof AccessDeniedHttpException,
                $exception instanceof AccessDeniedException => 'Access denied',
                $exception instanceof UnauthorizedHttpException,
                $exception instanceof AuthenticationException => 'Authentication required',
                $exception instanceof BadRequestHttpException => 'Bad request',
                $exception instanceof MethodNotAllowedHttpException => 'Method not allowed',
                $exception instanceof ValidationFailedException => 'Validation failed',
                $exception instanceof ConstraintViolationException => 'Data constraint violation',
                $exception instanceof ConnectionException => 'Service temporarily unavailable',
                $exception instanceof HttpException => $exception->getMessage(),
                default => 'Internal server error',
            };
        }

        // In development, return actual exception message
        return $exception->getMessage();
    }

    private function getErrorType(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof QuizApiException => $exception->getErrorType(),
            $exception instanceof NotFoundHttpException => 'NOT_FOUND',
            $exception instanceof AccessDeniedHttpException,
            $exception instanceof AccessDeniedException => 'FORBIDDEN',
            $exception instanceof UnauthorizedHttpException,
            $exception instanceof AuthenticationException => 'UNAUTHORIZED',
            $exception instanceof BadRequestHttpException => 'BAD_REQUEST',
            $exception instanceof MethodNotAllowedHttpException => 'METHOD_NOT_ALLOWED',
            $exception instanceof ValidationFailedException => 'VALIDATION_ERROR',
            $exception instanceof ConstraintViolationException => 'CONSTRAINT_VIOLATION',
            $exception instanceof ConnectionException => 'SERVICE_UNAVAILABLE',
            default => 'INTERNAL_ERROR',
        };
    }

        private function addSpecificErrorDetails(array &$errorData, \Throwable $exception): void
    {
        // Add custom Quiz API exception details
        if ($exception instanceof QuizValidationException) {
            $errorData['error']['validation_errors'] = $exception->getValidationErrors();
        }

        // Add validation errors details
        if ($exception instanceof ValidationFailedException) {
            $violations = $exception->getViolations();
            $errors = [];

            foreach ($violations as $violation) {
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                    'invalid_value' => $violation->getInvalidValue(),
                ];
            }

            $errorData['error']['validation_errors'] = $errors;
        }

        // Add constraint violation details
        if ($exception instanceof ConstraintViolationException) {
            $errorData['error']['constraint'] = [
                'type' => 'database_constraint',
                'details' => $this->environment === 'dev' ? $exception->getMessage() : 'Data integrity constraint violation'
            ];
        }

        // Add HTTP method details for method not allowed
        if ($exception instanceof MethodNotAllowedHttpException) {
            $errorData['error']['hint'] = 'Check the allowed HTTP methods for this endpoint';
        }
    }

    private function logException(\Throwable $exception, Request $request): void
    {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'request_uri' => $request->getRequestUri(),
            'method' => $request->getMethod(),
            'user_agent' => $request->headers->get('User-Agent'),
            'ip' => $request->getClientIp(),
        ];

        // Log level based on exception type
        $logLevel = match (true) {
            $exception instanceof NotFoundHttpException => 'info',
            $exception instanceof AccessDeniedHttpException,
            $exception instanceof UnauthorizedHttpException => 'warning',
            $exception instanceof BadRequestHttpException,
            $exception instanceof ValidationFailedException => 'notice',
            default => 'error',
        };

        $this->logger->log($logLevel, 'Exception occurred: ' . $exception->getMessage(), $context);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }
}
