<?php

namespace App\EventSubscriber;

use App\Entity\Quizz;
use App\Entity\Question;
use App\Service\QuizCacheService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;

/**
 * Automatically handles cache invalidation when entities change
 */
class QuizCacheSubscriber implements EventSubscriberInterface
{
    private array $updatedQuizzes = [];
    private array $questionsWithQuizzes = [];

    public function __construct(
        private QuizCacheService $cacheService,
        private ?LoggerInterface $logger = null
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
            Events::postFlush,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->trackEntity($args->getObject(), 'persist');
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->trackEntity($args->getObject(), 'update');
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->trackEntity($args->getObject(), 'remove');
    }

    public function postFlush(): void
    {
        $this->updateCaches();
    }

    /**
     * Track entity changes to update cache later
     */
    private function trackEntity(object $entity, string $operation): void
    {
        // Track quiz changes directly
        if ($entity instanceof Quizz) {
            $this->updatedQuizzes[$entity->getId()] = [
                'quiz' => $entity,
                'operation' => $operation
            ];
            $this->log("Tracked {$operation} for quiz #{$entity->getId()}");
            return;
        }

        // Track question changes to update parent quiz
        if ($entity instanceof Question) {
            $quiz = $entity->getQuizz();
            if ($quiz && $quiz->getId()) {
                $this->questionsWithQuizzes[$entity->getId()] = [
                    'quiz_id' => $quiz->getId(),
                    'quiz' => $quiz,
                    'operation' => $operation
                ];
                $this->log("Tracked question {$operation} for quiz #{$quiz->getId()}");
            }
            return;
        }
    }

    /**
     * Update caches after flush
     */
    private function updateCaches(): void
    {
        // Process quizzes first
        foreach ($this->updatedQuizzes as $quizId => $data) {
            try {
                $this->updateQuizCache($data['quiz'], $data['operation']);
            } catch (\Exception $e) {
                $this->log("Cache update failed for quiz #{$quizId}: " . $e->getMessage(), 'error');
            }
        }

        // Process quizzes from questions changes
        foreach ($this->questionsWithQuizzes as $questionId => $data) {
            // Skip if quiz was already handled directly
            if (isset($this->updatedQuizzes[$data['quiz_id']])) {
                continue;
            }

            try {
                $this->updateQuizCache($data['quiz'], $data['operation']);
            } catch (\Exception $e) {
                $this->log("Cache update failed for quiz #{$data['quiz_id']} (question #{$questionId}): " . $e->getMessage(), 'error');
            }
        }

        // Reset tracked entities
        $this->updatedQuizzes = [];
        $this->questionsWithQuizzes = [];
    }

    /**
     * Update quiz cache based on operation type
     */
    private function updateQuizCache(Quizz $quiz, string $operation): void
    {
        $quizId = $quiz->getId();

        if ($operation === 'remove') {
            $this->cacheService->invalidateQuizCache($quizId);
            $this->cacheService->invalidateListCaches();
            $this->log("Invalidated cache for removed quiz #{$quizId}");
        } else {
            // For persist/update, invalidate and then refresh cache
            $this->cacheService->invalidateQuizCache($quizId);
            $this->cacheService->cacheQuiz($quiz);
            $this->log("Refreshed cache for quiz #{$quizId} after {$operation}");

            // Also refresh list caches
            if ($operation === 'persist') {
                $this->cacheService->invalidateListCaches();
                $this->log("Invalidated list caches after new quiz #{$quizId}");
            }
        }
    }

    private function log(string $message, string $level = 'info'): void
    {
        if ($this->logger) {
            $this->logger->$level("[QuizCacheSubscriber] {$message}");
        }
    }
}
