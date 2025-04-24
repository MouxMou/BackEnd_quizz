<?php

namespace App\Controller;

use App\Entity\Question;
use App\Entity\Quizz;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

final class QuestionController extends AbstractController
{
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

        return new JsonResponse($serializer->serialize($question, 'json', ['groups' => 'quizz:read']), Response::HTTP_CREATED, [], json: true);
    }

    #[Route('api/v1/question/update/{id}', name: 'api_update_question', methods: ['PATCH'])]
    public function updateQuestion(Question $id, Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse {
        $updatedQuestion = $serializer->deserialize($request->getContent(), Question::class, 'json', [
            'object_to_populate' => $id,
        ]);
        $entityManager->persist($updatedQuestion);
        $entityManager->flush();

        return new JsonResponse($serializer->serialize($updatedQuestion, 'json', ['groups' => 'quizz:read']), Response::HTTP_OK, [], json: true);
    }

    #[Route('api/v1/question/delete/{id}', name: 'api_delete_question', methods: ['DELETE'])]
    public function deleteQuestion(Question $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if ('' !== $request->getContent() && true === $request->toArray()['hard']) {
            $entityManager->remove($id);
        }

        $entityManager->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
