<?php

namespace App\Controller;

use App\Entity\Question;
use App\Entity\Quizz;
use App\Service\QuizCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

final class QuestionController extends AbstractController
{
    public function __construct(
        private QuizCacheService $cacheService
    ) {}

    #[Route('api/v1/question/create/{quizzId}', name: 'api_add_question', methods: ['POST'])]
    public function addQuestion(int $quizzId, Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse {
        $question = $serializer->deserialize($request->getContent(), Question::class, 'json');
        $quizz = $entityManager->getRepository(Quizz::class)->find($quizzId);

        if (!$quizz) {
            return new JsonResponse(['error' => 'Quizz not found'], Response::HTTP_NOT_FOUND);
        }

        $question->setQuizz($quizz);
        $entityManager->persist($question);
        $entityManager->flush();

        // Invalidate cache for this quiz since we added a question
        $this->cacheService->invalidateQuizCache($quizzId);

        // Re-cache the quiz with the new question
        $this->cacheService->cacheQuiz($quizz);

        return new JsonResponse($serializer->serialize($question, 'json', ['groups' => 'quizz:read']), Response::HTTP_CREATED, [
            'Content-Type' => 'application/json'
        ], true);
    }

    #[Route('api/v1/question/update/{questionId}', name: 'api_update_question', methods: ['PATCH'])]
    public function updateQuestion(Question $questionId, Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse {
        $quizId = $questionId->getQuizz()->getId();

        $updatedQuestion = $serializer->deserialize($request->getContent(), Question::class, 'json', [
            'object_to_populate' => $questionId,
        ]);
        $entityManager->persist($updatedQuestion);
        $entityManager->flush();

        // Invalidate cache for the quiz that contains this question
        $this->cacheService->invalidateQuizCache($quizId);

        // Re-cache the quiz
        $this->cacheService->cacheQuiz($questionId->getQuizz());

        return new JsonResponse($serializer->serialize($updatedQuestion, 'json', ['groups' => 'quizz:read']), Response::HTTP_OK, [
            'Content-Type' => 'application/json'
        ], true);
    }

    #[Route('api/v1/question/delete/{questionId}', name: 'api_delete_question', methods: ['DELETE'])]
    public function deleteQuestion(Question $questionId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $quizId = $questionId->getQuizz()->getId();
        $quiz = $questionId->getQuizz();

        if ('' !== $request->getContent() && true === $request->toArray()['hard']) {
            $entityManager->remove($questionId);
        }

        $entityManager->flush();

        // Invalidate cache for the quiz that contained this question
        $this->cacheService->invalidateQuizCache($quizId);

        // Re-cache the quiz without the deleted question
        $this->cacheService->cacheQuiz($quiz);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get questions for a specific quiz (cached)
     */
    #[Route('api/v1/quizz/{quizzId}/questions', name: 'api_get_quiz_questions', methods: ['GET'])]
    public function getQuizQuestions(int $quizzId): JsonResponse
    {
        $quiz = $this->cacheService->getQuiz($quizzId);

        if (!$quiz) {
            return new JsonResponse(['error' => 'Quiz not found'], Response::HTTP_NOT_FOUND);
        }

        $questions = $quiz['questions'] ?? [];

        $response = new JsonResponse([
            'quiz_id' => $quizzId,
            'questions' => $questions,
            'total' => count($questions)
        ], Response::HTTP_OK);

        // Cache for 10 minutes
        $response->headers->set('Cache-Control', 'public, max-age=600');
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}
