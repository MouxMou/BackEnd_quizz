<?php

namespace App\Controller;

use App\Entity\Quizz;
use App\Repository\QuizzRepository;
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
    #[Route('api/v1/quizz', name: 'api_get_quizz', methods: ["GET"])]
    public function getAll(QuizzRepository $quizzRepository, SerializerInterface $serializer): JsonResponse
    {
        $quizzData = $quizzRepository->findAll();
        $jsonData = $serializer->serialize($quizzData, 'json', ['groups' => 'quizz:read']);
        return new JsonResponse($jsonData, Response::HTTP_OK, [], json:true);
    }

    #[Route('api/v1/quizz/{quizzId}', name: 'api_get_one_quizz', methods: ["GET"])]
    public function get(Quizz $quizzId, SerializerInterface $serializer): JsonResponse
    {
        $jsonData = $serializer->serialize($quizzId, 'json', ['groups' => 'quizz:read']);
        return new JsonResponse($jsonData, Response::HTTP_OK, [], json:true);
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
        $location = $urlGenerator->generate('get_quizz', ['id' => $quizz->getId(), UrlGeneratorInterface::ABSOLUTE_PATH]);
        $jsonData = $serializer->serialize($quizz, 'json', ['groups' => 'quizz:read']);
        return new JsonResponse($jsonData, Response::HTTP_CREATED, ["location" => $location], json:true);
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
        $jsonData = $serializer->serialize($updatedQuizz, 'json', ['groups' => 'quizz:read']);
        return new JsonResponse($jsonData, Response::HTTP_OK, [], json: true);
    }

    #[Route('api/v1/quizz/delete/{quizzId}', name: 'api_delete_quizz', methods: ['DELETE'])]
    public function quizzDelete(Quizz $quizzId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {

        //La version du prof
        if ('' !== $request->getContent() && true === $request->toArray()['hard']) {
            $entityManager->remove($quizzId);
        } else {
            $quizzId->setStatus('off');
            $entityManager->persist($quizzId);
        }
        $entityManager->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);

    }
}
