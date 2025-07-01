<?php

namespace App\Serializer\Normalizer;

use App\Entity\Quizz;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AutoDiscoveryNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function normalize($object, ?string $format = null, array $context = []): array
    {
        $data = $this->normalizer->normalize($object, $format, $context);
        $className = (new ReflectionClass($object))->getShortName();
        $className = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        $data["_link"] = [
            "up" => [
                "method" => ['GET'],
                "path" =>  $this->urlGenerator->generate('api_get_quizz')
            ],
            "self" => [
                "method" => ['GET'],
                "path" =>  $this->urlGenerator->generate('api_get_one_quizz', ["quizzId" => $data["id"]])
            ]
        ];

        return $data;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return ($data instanceof Quizz) && $format === true;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Quizz::class => true];
    }
}
