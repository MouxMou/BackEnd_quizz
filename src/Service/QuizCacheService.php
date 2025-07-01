<?php

namespace App\Service;

use App\Entity\Quizz;
use App\Entity\Question;
use App\Repository\QuizzRepository;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

class QuizCacheService
{
    private const CACHE_TAG_PREFIX = 'quiz_';
    private const LIST_CACHE_KEY = 'quiz_list';
    private const SHORT_TTL = 900; // 15 minutes
    private const LONG_TTL = 86400; // 24 hours

    // Tags pour l'invalidation intelligente
    private const TAG_QUIZ = 'quiz';
    private const TAG_QUIZ_LIST = 'quiz_list';
    private const TAG_QUESTIONS = 'questions';

    public function __construct(
        private CacheInterface $quizCache,
        private CacheInterface $quizShortCache,
        private CacheInterface $quizLongCache,
        private SerializerInterface $serializer,
        private QuizzRepository $quizzRepository,
        private EntityManagerInterface $entityManager,
        private ?LoggerInterface $logger = null,
        private bool $appDebug = false
    ) {}

    /**
     * Get a quiz from cache or load it from database
     */
    public function getQuiz(int $id, bool $useCache = true): ?array
    {
        if (!$useCache || $this->appDebug) {
            return $this->loadQuizFromDatabase($id);
        }

        $cacheKey = $this->getQuizCacheKey($id);

        return $this->quizCache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter(3600); // 1 hour
            $this->log("Cache miss - loading quiz #{$id} from database");

            $quizData = $this->loadQuizFromDatabase($id);

            if (!$quizData) {
                $this->log("Quiz #{$id} not found in database");
                // Cache negative result for short time to avoid repeated DB queries
                $item->expiresAfter(300);
                return null;
            }

            return $quizData;
        });
    }

    /**
     * Cache a specific quiz
     */
    public function cacheQuiz(Quizz $quiz): void
    {
        $cacheKey = $this->getQuizCacheKey($quiz->getId());

        // Delete existing cache to force refresh
        $this->quizCache->delete($cacheKey);

        // Pre-warm with new data
        $this->getQuiz($quiz->getId(), true);

        $this->log("Cached quiz #{$quiz->getId()}");
    }

    /**
     * Invalidate cache for a specific quiz
     */
    public function invalidateQuizCache(int $id): void
    {
        $cacheKey = $this->getQuizCacheKey($id);
        $this->quizCache->delete($cacheKey);
        $this->log("Invalidated cache for quiz #{$id}");
    }

    /**
     * Get all quizzes from cache or load from database
     */
    public function getAllQuizzes(bool $useCache = true): array
    {
        if (!$useCache || $this->appDebug) {
            return $this->loadAllQuizzesFromDatabase();
        }

        return $this->quizShortCache->get(self::LIST_CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::SHORT_TTL);
            $this->log("Cache miss - loading all quizzes from database");

            return $this->loadAllQuizzesFromDatabase();
        });
    }

    /**
     * Invalidate list cache
     */
    public function invalidateListCaches(): void
    {
        $this->quizShortCache->delete(self::LIST_CACHE_KEY);
        $this->log("Invalidated quiz list cache");
    }

    /**
     * Get statistics about the cache
     */
    public function getStats(): array
    {
        $cachedQuizzes = $this->getCachedQuizzesList();

        return [
            'quiz_count' => count($cachedQuizzes),
            'short_ttl' => self::SHORT_TTL,
            'long_ttl' => self::LONG_TTL,
            'short_cache_items' => count($cachedQuizzes),
            'long_cache_items' => count($cachedQuizzes),
        ];
    }

    /**
     * Get a list of all cached quizzes with metadata
     */
    public function getCachedQuizzesList(): array
    {
        $result = [];
        $quizzes = $this->loadAllQuizzesFromDatabase();

        foreach ($quizzes as $quiz) {
            $cacheKey = $this->getQuizCacheKey($quiz['id']);

            // Check if the item exists in cache (simple check)
            try {
                $cachedData = $this->quizCache->get($cacheKey, function () {
                    return null; // Return null if not in cache
                });

                if ($cachedData !== null) {
                    $result[] = [
                        'id' => $cachedData['id'],
                        'name' => $cachedData['name'],
                        'question_count' => count($cachedData['questions'] ?? []),
                        'cached_at' => new \DateTime(),
                    ];
                }
            } catch (\Exception $e) {
                // Cache miss or error, skip this quiz
                continue;
            }
        }

        return $result;
    }

    /**
     * Clear all caches
     */
    public function clearAllCaches(): void
    {
        // Clear quiz cache
        $quizzes = $this->loadAllQuizzesFromDatabase();
        foreach ($quizzes as $quiz) {
            $this->invalidateQuizCache($quiz['id']);
        }

        // Clear list cache
        $this->invalidateListCaches();

        $this->log("Cleared all caches");
    }

    /**
     * Warm up cache with all quizzes
     */
    public function warmupCache(): int
    {
        $quizzes = $this->quizzRepository->findAll();
        $count = 0;

        foreach ($quizzes as $quiz) {
            $this->getQuiz($quiz->getId(), true); // Force cache
            $count++;
        }

        // Also cache the list
        $this->getAllQuizzes(true);

        $this->log("Warmed up cache with {$count} quizzes");
        return $count;
    }

    /**
     * Refresh cache for a specific quiz
     */
    public function refreshQuizCache(int $id): bool
    {
        $quiz = $this->quizzRepository->find($id);

        if (!$quiz) {
            $this->log("Cannot refresh quiz #{$id} - not found", 'warning');
            return false;
        }

        $this->invalidateQuizCache($id);
        $this->getQuiz($id, true); // Force reload
        $this->log("Refreshed cache for quiz #{$id}");

        return true;
    }

    /**
     * Load a quiz from the database
     */
    private function loadQuizFromDatabase(int $id): ?array
    {
        $quiz = $this->quizzRepository->find($id);

        if (!$quiz) {
            return null;
        }

        return $this->serializeQuiz($quiz);
    }

    /**
     * Load all quizzes from the database
     */
    private function loadAllQuizzesFromDatabase(): array
    {
        $quizzes = $this->quizzRepository->findAll();
        $result = [];

        foreach ($quizzes as $quiz) {
            $result[] = [
                'id' => $quiz->getId(),
                'name' => $quiz->getName(),
                'status' => $quiz->getStatus(),
                'questionCount' => count($quiz->getQuestions()),
            ];
        }

        return $result;
    }

    /**
     * Serialize a quiz entity to an array for caching
     */
    private function serializeQuiz(Quizz $quiz): array
    {
        // Force initialization of the collection
        $questions = $quiz->getQuestions()->toArray();

        $questionsData = [];
        foreach ($questions as $question) {
            $answers = $question->getAnswers()->toArray();

            $answersData = [];
            foreach ($answers as $answer) {
                $answersData[] = [
                    'id' => $answer->getId(),
                    'content' => $answer->getContent(),
                    'isCorrect' => $answer->getIsCorrect(),
                ];
            }

            $questionsData[] = [
                'id' => $question->getId(),
                'content' => $question->getContent(),
                'explanation' => $question->getExplanation(),
                'mediaUrl' => $question->getMediaUrl(),
                'timeToAnswer' => $question->getTimeToAnswer() ? $question->getTimeToAnswer()->format('H:i:s') : null,
                'answers' => $answersData,
            ];
        }

        return [
            'id' => $quiz->getId(),
            'name' => $quiz->getName(),
            'status' => $quiz->getStatus(),
            'questions' => $questionsData,
        ];
    }

    /**
     * Get the cache key for a quiz
     */
    private function getQuizCacheKey(int $id): string
    {
        return 'quiz_' . $id;
    }

    /**
     * Log a message
     */
    private function log(string $message, string $level = 'debug'): void
    {
        if ($this->logger) {
            $this->logger->$level("[QuizCache] " . $message);
        }
    }
}
