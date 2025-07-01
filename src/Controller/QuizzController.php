<?php

namespace App\Controller;

use App\Entity\Quizz;
use App\Repository\QuizzRepository;
use App\Service\QuizCacheService;
use App\Traits\ExceptionHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class QuizzController extends AbstractController
{
    use ExceptionHelperTrait;

    public function __construct(
        private QuizCacheService $cacheService
    ) {}

    #[Route('api/v1/quizz', name: 'api_get_quizz', methods: ["GET"])]
    public function getAll(Request $request): JsonResponse
    {
        // Use cache service for all quizzes
        $quizzData = $this->cacheService->getAllQuizzes();

        $response = new JsonResponse($quizzData, Response::HTTP_OK);

        // Add cache headers for client-side caching
        $response->headers->set('Cache-Control', 'public, max-age=300'); // 5 minutes
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    #[Route('api/v1/quizz/{quizzId}', name: 'api_get_one_quizz', methods: ["GET"])]
    public function get(int $quizzId, Request $request): JsonResponse
    {
        // Get quiz from cache service
        $quizzData = $this->cacheService->getQuiz($quizzId);

        // Use the custom exception instead of returning JSON error
        if (!$quizzData) {
            $this->throwQuizNotFound($quizzId);
        }

        $response = new JsonResponse($quizzData, Response::HTTP_OK);

        // Cache headers
        $response->headers->set('Cache-Control', 'public, max-age=600'); // 10 minutes
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    #[Route('api/v1/quizz/create', name: 'api_create_quizz', methods: ["POST"])]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator
    ): JsonResponse
    {
        $quizz = $serializer->deserialize($request->getContent(), Quizz::class, 'json');
        $errors = $validator->validate($quizz);
        if ($errors->count() > 0) {
            $jsonErrors = $serializer->serialize($errors, 'json');
            return new JsonResponse($jsonErrors, Response::HTTP_BAD_REQUEST, [], true);
        }

        $entityManager->persist($quizz);
        $entityManager->flush();

        // Cache the new quiz
        $this->cacheService->cacheQuiz($quizz);

        // Invalidate list caches since we added a new quiz
        $this->cacheService->invalidateListCaches();

        $location = $urlGenerator->generate('api_get_one_quizz', ['quizzId' => $quizz->getId()], UrlGeneratorInterface::ABSOLUTE_PATH);
        $jsonData = $serializer->serialize($quizz, 'json', ['groups' => 'quizz:read']);

        return new JsonResponse($jsonData, Response::HTTP_CREATED, [
            "Location" => $location,
            "Content-Type" => "application/json"
        ], true);
    }

    #[Route(path: 'api/v1/quizz/update/{quizzId}', name: 'api_update_quizz', methods: ['PATCH'])]
    public function update(
        Quizz $quizzId,
        Request $request,
        UrlGeneratorInterface $urlGenerator,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse
    {
        $updatedQuizz = $serializer->deserialize($request->getContent(), Quizz::class, 'json', ['object_to_populate' => $quizzId]);
        $errors = $validator->validate($updatedQuizz);
        if ($errors->count() > 0) {
            $jsonErrors = $serializer->serialize($errors, 'json');
            return new JsonResponse($jsonErrors, Response::HTTP_BAD_REQUEST, [], true);
        }

        $entityManager->persist($updatedQuizz);
        $entityManager->flush();

        // Invalidate and refresh cache for this quiz
        $this->cacheService->invalidateQuizCache($updatedQuizz->getId());
        $this->cacheService->cacheQuiz($updatedQuizz);

        $jsonData = $serializer->serialize($updatedQuizz, 'json', ['groups' => 'quizz:read']);

        return new JsonResponse($jsonData, Response::HTTP_OK, [
            "Content-Type" => "application/json"
        ], true);
    }

    #[Route('api/v1/quizz/delete/{quizzId}', name: 'api_delete_quizz', methods: ['DELETE'])]
    public function quizzDelete(Quizz $quizzId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $quizId = $quizzId->getId();

        //La version du prof
        if ('' !== $request->getContent() && true === $request->toArray()['hard']) {
            $entityManager->remove($quizzId);
        } else {
            $quizzId->setStatus('off');
            $entityManager->persist($quizzId);
        }
        $entityManager->flush();

        // Invalidate all caches for this quiz
        $this->cacheService->invalidateQuizCache($quizId);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Endpoint to get cache statistics (for monitoring)
     */
    #[Route('api/v1/quizz/cache/stats', name: 'api_cache_stats', methods: ['GET'])]
    public function getCacheStats(): JsonResponse
    {
        $stats = $this->cacheService->getStats();

        return new JsonResponse($stats, Response::HTTP_OK);
    }

    /**
     * Endpoint to warm up cache (for performance)
     */
    #[Route('api/v1/quizz/cache/warmup', name: 'api_cache_warmup', methods: ['POST'])]
    public function warmupCache(QuizzRepository $quizzRepository): JsonResponse
    {
        // Get popular quizzes and warm up their cache
        $popularQuizzes = $quizzRepository->findBy(['status' => 'active'], ['createdAt' => 'DESC'], 10);

        $warmedUp = [];
        foreach ($popularQuizzes as $quiz) {
            $this->cacheService->cacheQuiz($quiz);
            $warmedUp[] = $quiz->getId();
        }

        return new JsonResponse([
            'message' => 'Cache warmed up successfully',
            'quizzes_cached' => $warmedUp,
            'count' => count($warmedUp)
        ], Response::HTTP_OK);
    }
}
