<?php

namespace App\DataFixtures;

use App\Entity\Quizz;
use App\Entity\Song;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

class AppFixtures extends Fixture
{
    private Generator $faker;

    public function __construct(){
        $this->faker = Factory::create('fr_FR');
    }

    public function load(ObjectManager $manager): void
    {
        for ($i = 0; $i < 100; $i++) {
            $quizz = new Quizz();
            $quizz->setName($this->faker->sentence(2))
                ->setStatus('on');
            $manager->persist($quizz);
        }

        $manager->flush();
    }
}
