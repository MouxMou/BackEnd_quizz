<?php

namespace App\DataFixtures;

use App\Entity\Answer;
use App\Entity\Question;
use App\Entity\Quizz;
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
        // Quiz 1: Culture Générale
        $quiz1 = new Quizz();
        $quiz1->setName('Quiz de Culture Générale')
            ->setStatus('active');
        $manager->persist($quiz1);

        $this->createQuestionsForQuiz($manager, $quiz1, [
            [
                'text' => 'Quelle est la capitale de la France ?',
                'timeToAnswer' => '00:01:30',
                'mediaUrl' => 'https://example.com/images/paris-tour-eiffel.jpg',
                'answers' => [
                    ['text' => 'Paris', 'isCorrect' => true],
                    ['text' => 'Lyon', 'isCorrect' => false],
                    ['text' => 'Marseille', 'isCorrect' => false],
                    ['text' => 'Toulouse', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Qui a peint la Joconde ?',
                'timeToAnswer' => '00:02:00',
                'mediaUrl' => 'https://example.com/images/mona-lisa.jpg',
                'answers' => [
                    ['text' => 'Pablo Picasso', 'isCorrect' => false],
                    ['text' => 'Leonardo da Vinci', 'isCorrect' => true],
                    ['text' => 'Vincent van Gogh', 'isCorrect' => false],
                    ['text' => 'Claude Monet', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Combien de continents y a-t-il sur Terre ?',
                'timeToAnswer' => '00:01:15',
                'mediaUrl' => null,
                'answers' => [
                    ['text' => '5', 'isCorrect' => false],
                    ['text' => '6', 'isCorrect' => false],
                    ['text' => '7', 'isCorrect' => true],
                    ['text' => '8', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Quel est le plus grand océan du monde ?',
                'timeToAnswer' => '00:01:45',
                'mediaUrl' => 'https://example.com/images/pacific-ocean.jpg',
                'answers' => [
                    ['text' => 'Océan Atlantique', 'isCorrect' => false],
                    ['text' => 'Océan Indien', 'isCorrect' => false],
                    ['text' => 'Océan Arctique', 'isCorrect' => false],
                    ['text' => 'Océan Pacifique', 'isCorrect' => true]
                ]
            ],
            [
                'text' => 'En quelle année a eu lieu la chute du mur de Berlin ?',
                'timeToAnswer' => '00:02:30',
                'mediaUrl' => 'https://example.com/images/berlin-wall.jpg',
                'answers' => [
                    ['text' => '1987', 'isCorrect' => false],
                    ['text' => '1989', 'isCorrect' => true],
                    ['text' => '1991', 'isCorrect' => false],
                    ['text' => '1993', 'isCorrect' => false]
                ]
            ]
        ]);

        // Quiz 2: Sciences et Technologies
        $quiz2 = new Quizz();
        $quiz2->setName('Quiz Sciences et Technologies')
            ->setStatus('active');
        $manager->persist($quiz2);

        $this->createQuestionsForQuiz($manager, $quiz2, [
            [
                'text' => 'Quelle est la formule chimique de l\'eau ?',
                'timeToAnswer' => '00:01:00',
                'mediaUrl' => 'https://example.com/images/water-molecule.png',
                'answers' => [
                    ['text' => 'H2O', 'isCorrect' => true],
                    ['text' => 'CO2', 'isCorrect' => false],
                    ['text' => 'O2', 'isCorrect' => false],
                    ['text' => 'H2SO4', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Qui a développé la théorie de la relativité ?',
                'timeToAnswer' => '00:01:30',
                'mediaUrl' => 'https://example.com/images/einstein.jpg',
                'answers' => [
                    ['text' => 'Isaac Newton', 'isCorrect' => false],
                    ['text' => 'Albert Einstein', 'isCorrect' => true],
                    ['text' => 'Nikola Tesla', 'isCorrect' => false],
                    ['text' => 'Stephen Hawking', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Combien de chromosomes possède un être humain ?',
                'timeToAnswer' => '00:02:00',
                'mediaUrl' => null,
                'answers' => [
                    ['text' => '44', 'isCorrect' => false],
                    ['text' => '46', 'isCorrect' => true],
                    ['text' => '48', 'isCorrect' => false],
                    ['text' => '50', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Quel langage de programmation a été créé par Brendan Eich ?',
                'timeToAnswer' => '00:01:45',
                'mediaUrl' => 'https://example.com/images/javascript-logo.png',
                'answers' => [
                    ['text' => 'Python', 'isCorrect' => false],
                    ['text' => 'Java', 'isCorrect' => false],
                    ['text' => 'JavaScript', 'isCorrect' => true],
                    ['text' => 'C++', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Quelle est la vitesse de la lumière dans le vide ?',
                'timeToAnswer' => '00:02:15',
                'mediaUrl' => 'https://example.com/images/light-speed.gif',
                'answers' => [
                    ['text' => '300 000 km/s', 'isCorrect' => true],
                    ['text' => '150 000 km/s', 'isCorrect' => false],
                    ['text' => '450 000 km/s', 'isCorrect' => false],
                    ['text' => '200 000 km/s', 'isCorrect' => false]
                ]
            ]
        ]);

        // Quiz 3: Sport et Loisirs
        $quiz3 = new Quizz();
        $quiz3->setName('Quiz Sport et Loisirs')
            ->setStatus('active');
        $manager->persist($quiz3);

        $this->createQuestionsForQuiz($manager, $quiz3, [
            [
                'text' => 'Combien de joueurs composent une équipe de football sur le terrain ?',
                'timeToAnswer' => '00:01:00',
                'mediaUrl' => 'https://example.com/images/football-team.jpg',
                'answers' => [
                    ['text' => '10', 'isCorrect' => false],
                    ['text' => '11', 'isCorrect' => true],
                    ['text' => '12', 'isCorrect' => false],
                    ['text' => '9', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Dans quel sport utilise-t-on un volant ?',
                'timeToAnswer' => '00:01:20',
                'mediaUrl' => 'https://example.com/images/badminton-shuttlecock.jpg',
                'answers' => [
                    ['text' => 'Tennis', 'isCorrect' => false],
                    ['text' => 'Ping-pong', 'isCorrect' => false],
                    ['text' => 'Badminton', 'isCorrect' => true],
                    ['text' => 'Squash', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Quel pays a remporté la Coupe du Monde de Football 2018 ?',
                'timeToAnswer' => '00:01:30',
                'mediaUrl' => 'https://example.com/images/world-cup-2018.jpg',
                'answers' => [
                    ['text' => 'Brésil', 'isCorrect' => false],
                    ['text' => 'Allemagne', 'isCorrect' => false],
                    ['text' => 'France', 'isCorrect' => true],
                    ['text' => 'Argentine', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Combien de points vaut un panier à 3 points au basketball ?',
                'timeToAnswer' => '00:00:45',
                'mediaUrl' => null,
                'answers' => [
                    ['text' => '2 points', 'isCorrect' => false],
                    ['text' => '3 points', 'isCorrect' => true],
                    ['text' => '4 points', 'isCorrect' => false],
                    ['text' => '5 points', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Quelle est la distance officielle d\'un marathon ?',
                'timeToAnswer' => '00:01:45',
                'mediaUrl' => 'https://example.com/images/marathon-runners.jpg',
                'answers' => [
                    ['text' => '40,195 km', 'isCorrect' => false],
                    ['text' => '42,195 km', 'isCorrect' => true],
                    ['text' => '41,195 km', 'isCorrect' => false],
                    ['text' => '43,195 km', 'isCorrect' => false]
                ]
            ]
        ]);

        // Quiz 4: Géographie et Voyages
        $quiz4 = new Quizz();
        $quiz4->setName('Quiz Géographie et Voyages')
            ->setStatus('active');
        $manager->persist($quiz4);

        $this->createQuestionsForQuiz($manager, $quiz4, [
            [
                'text' => 'Quelle est la capitale de l\'Australie ?',
                'timeToAnswer' => '00:02:00',
                'mediaUrl' => 'https://example.com/images/canberra.jpg',
                'answers' => [
                    ['text' => 'Sydney', 'isCorrect' => false],
                    ['text' => 'Melbourne', 'isCorrect' => false],
                    ['text' => 'Canberra', 'isCorrect' => true],
                    ['text' => 'Perth', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Quel est le plus haut sommet du monde ?',
                'timeToAnswer' => '00:01:30',
                'mediaUrl' => 'https://example.com/images/everest.jpg',
                'answers' => [
                    ['text' => 'K2', 'isCorrect' => false],
                    ['text' => 'Mont Blanc', 'isCorrect' => false],
                    ['text' => 'Everest', 'isCorrect' => true],
                    ['text' => 'Annapurna', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Dans quel pays se trouve Machu Picchu ?',
                'timeToAnswer' => '00:01:45',
                'mediaUrl' => 'https://example.com/images/machu-picchu.jpg',
                'answers' => [
                    ['text' => 'Bolivie', 'isCorrect' => false],
                    ['text' => 'Pérou', 'isCorrect' => true],
                    ['text' => 'Équateur', 'isCorrect' => false],
                    ['text' => 'Colombie', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Quel fleuve traverse Paris ?',
                'timeToAnswer' => '00:01:00',
                'mediaUrl' => 'https://example.com/images/seine-paris.jpg',
                'answers' => [
                    ['text' => 'La Loire', 'isCorrect' => false],
                    ['text' => 'Le Rhône', 'isCorrect' => false],
                    ['text' => 'La Seine', 'isCorrect' => true],
                    ['text' => 'La Garonne', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Combien de fuseaux horaires compte la Russie ?',
                'timeToAnswer' => '00:02:30',
                'mediaUrl' => null,
                'answers' => [
                    ['text' => '9', 'isCorrect' => false],
                    ['text' => '11', 'isCorrect' => true],
                    ['text' => '13', 'isCorrect' => false],
                    ['text' => '15', 'isCorrect' => false]
                ]
            ]
        ]);

        // Quiz 5: Cuisine et Gastronomie
        $quiz5 = new Quizz();
        $quiz5->setName('Quiz Cuisine et Gastronomie')
            ->setStatus('draft');
        $manager->persist($quiz5);

        $this->createQuestionsForQuiz($manager, $quiz5, [
            [
                'text' => 'Quel est l\'ingrédient principal du guacamole ?',
                'timeToAnswer' => '00:01:15',
                'mediaUrl' => 'https://example.com/images/avocado.jpg',
                'answers' => [
                    ['text' => 'Tomate', 'isCorrect' => false],
                    ['text' => 'Avocat', 'isCorrect' => true],
                    ['text' => 'Concombre', 'isCorrect' => false],
                    ['text' => 'Poivron', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Quelle épice donne sa couleur jaune au curry ?',
                'timeToAnswer' => '00:01:30',
                'mediaUrl' => 'https://example.com/videos/turmeric-spice.mp4',
                'answers' => [
                    ['text' => 'Safran', 'isCorrect' => false],
                    ['text' => 'Curcuma', 'isCorrect' => true],
                    ['text' => 'Paprika', 'isCorrect' => false],
                    ['text' => 'Curry', 'isCorrect' => false]
                ]
            ],
            [
                'text' => 'Dans quelle région française est produit le champagne ?',
                'timeToAnswer' => '00:02:00',
                'mediaUrl' => 'https://example.com/images/champagne-region.jpg',
                'answers' => [
                    ['text' => 'Bordeaux', 'isCorrect' => false],
                    ['text' => 'Bourgogne', 'isCorrect' => false],
                    ['text' => 'Champagne-Ardenne', 'isCorrect' => true],
                    ['text' => 'Alsace', 'isCorrect' => false]
                ]
            ]
        ]);

        $manager->flush();
    }

    private function createQuestionsForQuiz(ObjectManager $manager, Quizz $quiz, array $questionsData): void
    {
        foreach ($questionsData as $index => $questionData) {
            $question = new Question();
            $question->setText($questionData['text'])
                ->setPosition($index + 1)
                ->setQuizz($quiz);

            // Ajouter le temps de réponse si spécifié
            if (isset($questionData['timeToAnswer']) && $questionData['timeToAnswer']) {
                $timeToAnswer = \DateTime::createFromFormat('H:i:s', $questionData['timeToAnswer']);
                $question->setTimeToAnswer($timeToAnswer);
            }

            // Ajouter l'URL du média si spécifiée
            if (isset($questionData['mediaUrl'])) {
                $question->setMediaUrl($questionData['mediaUrl']);
            }

            $manager->persist($question);

            foreach ($questionData['answers'] as $answerData) {
                $answer = new Answer();
                $answer->setText($answerData['text'])
                    ->setIsCorrect($answerData['isCorrect'])
                    ->setQuestion($question);

                $manager->persist($answer);
            }
        }
    }
}
