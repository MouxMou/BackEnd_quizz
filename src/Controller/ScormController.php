<?php

namespace App\Controller;

use App\Entity\Quizz;
use App\Repository\QuizzRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

final class ScormController extends AbstractController
{
    #[Route('/api/v1/scorm/generate/{id}', name: 'api_generate_scorm', methods: ['GET'])]
    public function generateScorm(
        Quizz $id,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager
    ): Response {
        try {
            $quizz = $entityManager->getRepository(Quizz::class)->find($id->getId());

            if (!$quizz) {
                return new JsonResponse(['error' => 'Quiz not found'], Response::HTTP_NOT_FOUND);
            }

            $tempDir = sys_get_temp_dir() . '/scorm_' . uniqid();
            mkdir($tempDir, 0777, true);

            $this->generateManifest($quizz, $tempDir);
            $this->generateIndexFile($quizz, $tempDir);
            $this->generateQuizContent($quizz, $tempDir, $serializer);
            $this->generateScormApi($tempDir);

            $zipPath = $this->createZipArchive($quizz, $tempDir);

            $this->cleanupTempDir($tempDir);

            $response = new BinaryFileResponse($zipPath);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'quiz_' . $quizz->getId() . '_scorm.zip'
            );

            $response->deleteFileAfterSend(true);

            return $response;

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to generate SCORM package',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/v1/scorm/preview/{id}', name: 'api_preview_scorm_data', methods: ['GET'])]
    public function previewScormData(
        Quizz $id,
        SerializerInterface $serializer
    ): JsonResponse {
        $scormData = [
            'quiz' => $serializer->serialize($id, 'json', ['groups' => 'quizz:read']),
            'manifest_info' => [
                'identifier' => 'quiz_' . $id->getId(),
                'title' => $id->getName() ?? 'Quiz SCORM',
                'version' => '1.0',
                'scorm_version' => '2004 4th Edition'
            ]
        ];

        return new JsonResponse($scormData, Response::HTTP_OK, [], true);
    }

    private function generateManifest(Quizz $quizz, string $tempDir): void
    {
        $manifestContent = '<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="quiz_' . $quizz->getId() . '" version="1.0"
          xmlns="http://www.imsglobal.org/xsd/imscp_v1p1"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3"
          xmlns:adlseq="http://www.adlnet.org/xsd/adlseq_v1p3"
          xmlns:adlnav="http://www.adlnet.org/xsd/adlnav_v1p3"
          xmlns:imsss="http://www.imsglobal.org/xsd/imsss">

  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>2004 4th Edition</schemaversion>
  </metadata>

  <organizations default="quiz_' . $quizz->getId() . '_org">
    <organization identifier="quiz_' . $quizz->getId() . '_org">
      <title>' . htmlspecialchars($quizz->getName() ?? 'Quiz') . '</title>
      <item identifier="quiz_' . $quizz->getId() . '_item" identifierref="quiz_' . $quizz->getId() . '_resource">
        <title>' . htmlspecialchars($quizz->getName() ?? 'Quiz') . '</title>
        <adlcp:timeLimitAction>continue,no message</adlcp:timeLimitAction>
        <adlcp:dataFromLMS>' . htmlspecialchars($quizz->getName() ?? '') . '</adlcp:dataFromLMS>
      </item>
    </organization>
  </organizations>

  <resources>
    <resource identifier="quiz_' . $quizz->getId() . '_resource" type="webcontent" adlcp:scormType="sco" href="index.html">
      <file href="index.html"/>
      <file href="quiz_data.js"/>
      <file href="scorm_api.js"/>
      <file href="styles.css"/>
    </resource>
  </resources>

</manifest>';

        file_put_contents($tempDir . '/imsmanifest.xml', $manifestContent);
    }

    private function generateIndexFile(Quizz $quizz, string $tempDir): void
    {
        $indexContent = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
         <title>' . htmlspecialchars($quizz->getName() ?? 'Quiz') . '</title>
     <link rel="stylesheet" href="styles.css">
     <script src="scorm_api.js"></script>
     <script src="quiz_data.js"></script>
 </head>
 <body>
     <div class="quiz-container">
         <header>
             <h1>' . htmlspecialchars($quizz->getName() ?? 'Quiz') . '</h1>
             <p class="description">' . htmlspecialchars($quizz->getName() ?? '') . '</p>
         </header>

        <main id="quiz-content">
            <div id="question-container" style="display: none;">
                <div id="question-number"></div>
                <div id="question-text"></div>
                <div id="answers-container"></div>
                <div class="navigation">
                    <button id="prev-btn" onclick="previousQuestion()">Précédent</button>
                    <button id="next-btn" onclick="nextQuestion()">Suivant</button>
                    <button id="submit-btn" onclick="submitQuiz()" style="display: none;">Terminer</button>
                </div>
            </div>

            <div id="start-screen">
                <button onclick="startQuiz()" class="start-btn">Commencer le Quiz</button>
            </div>

            <div id="results-screen" style="display: none;">
                <h2>Résultats</h2>
                <div id="score-display"></div>
                <button onclick="restartQuiz()" class="restart-btn">Recommencer</button>
            </div>
        </main>
    </div>

    <script>
        let currentQuestion = 0;
        let userAnswers = [];
        let score = 0;

        scormAPI.initialize();

        function startQuiz() {
            document.getElementById("start-screen").style.display = "none";
            document.getElementById("question-container").style.display = "block";
            scormAPI.setValue("cmi.core.lesson_status", "incomplete");
            showQuestion(0);
        }

        function showQuestion(index) {
            if (index >= quizData.questions.length) return;

            const question = quizData.questions[index];
            document.getElementById("question-number").textContent = `Question ${index + 1} / ${quizData.questions.length}`;
            document.getElementById("question-text").textContent = question.question;

            const answersContainer = document.getElementById("answers-container");
            answersContainer.innerHTML = "";

            question.answers.forEach((answer, answerIndex) => {
                const answerDiv = document.createElement("div");
                answerDiv.className = "answer-option";
                answerDiv.innerHTML = `
                    <input type="radio" name="question_${index}" value="${answerIndex}" id="answer_${answerIndex}">
                    <label for="answer_${answerIndex}">${answer.text}</label>
                `;
                answersContainer.appendChild(answerDiv);
            });

            document.getElementById("prev-btn").style.display = index > 0 ? "inline-block" : "none";
            document.getElementById("next-btn").style.display = index < quizData.questions.length - 1 ? "inline-block" : "none";
            document.getElementById("submit-btn").style.display = index === quizData.questions.length - 1 ? "inline-block" : "none";
        }

        function nextQuestion() {
            saveCurrentAnswer();
            currentQuestion++;
            showQuestion(currentQuestion);
        }

        function previousQuestion() {
            saveCurrentAnswer();
            currentQuestion--;
            showQuestion(currentQuestion);
        }

        function saveCurrentAnswer() {
            const selectedAnswer = document.querySelector(`input[name="question_${currentQuestion}"]:checked`);
            if (selectedAnswer) {
                userAnswers[currentQuestion] = parseInt(selectedAnswer.value);
            }
        }

        function submitQuiz() {
            saveCurrentAnswer();
            calculateScore();
            showResults();

            // Mise à jour SCORM
            scormAPI.setValue("cmi.core.score.raw", score);
            scormAPI.setValue("cmi.core.score.max", quizData.questions.length);
            scormAPI.setValue("cmi.core.lesson_status", score >= quizData.questions.length * 0.7 ? "passed" : "failed");
            scormAPI.commit();
        }

        function calculateScore() {
            score = 0;
            quizData.questions.forEach((question, index) => {
                if (userAnswers[index] !== undefined) {
                    const correctAnswer = question.answers.findIndex(answer => answer.isCorrect);
                    if (userAnswers[index] === correctAnswer) {
                        score++;
                    }
                }
            });
        }

        function showResults() {
            document.getElementById("question-container").style.display = "none";
            document.getElementById("results-screen").style.display = "block";

            const percentage = Math.round((score / quizData.questions.length) * 100);
            document.getElementById("score-display").innerHTML = `
                <p>Score: ${score} / ${quizData.questions.length}</p>
                <p>Pourcentage: ${percentage}%</p>
                <p class="${percentage >= 70 ? "success" : "failure"}">
                    ${percentage >= 70 ? "Félicitations ! Vous avez réussi." : "Vous devez obtenir au moins 70% pour réussir."}
                </p>
            `;
        }

        function restartQuiz() {
            currentQuestion = 0;
            userAnswers = [];
            score = 0;
            document.getElementById("results-screen").style.display = "none";
            document.getElementById("start-screen").style.display = "block";
            scormAPI.setValue("cmi.core.lesson_status", "not attempted");
        }
    </script>
</body>
</html>';

        file_put_contents($tempDir . '/index.html', $indexContent);
    }

    private function generateQuizContent(Quizz $quizz, string $tempDir, SerializerInterface $serializer): void
    {
        $quizData = [
            'id' => $quizz->getId(),
            'title' => $quizz->getName(),
            'description' => $quizz->getName(),
            'questions' => []
        ];

        foreach ($quizz->getQuestions() as $question) {
            $questionData = [
                'id' => $question->getId(),
                'question' => $question->getText(),
                'answers' => []
            ];

            foreach ($question->getAnswers() as $answer) {
                $questionData['answers'][] = [
                    'id' => $answer->getId(),
                    'text' => $answer->getText(),
                    'isCorrect' => $answer->isCorrect()
                ];
            }

            $quizData['questions'][] = $questionData;
        }

        $jsContent = 'const quizData = ' . json_encode($quizData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . ';';
        file_put_contents($tempDir . '/quiz_data.js', $jsContent);

        // Générer le CSS
        $cssContent = '
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 20px;
    background-color: #f5f5f5;
}

.quiz-container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

header {
    background: #007bff;
    color: white;
    padding: 30px;
    text-align: center;
}

header h1 {
    margin: 0;
    font-size: 2em;
}

.description {
    margin: 10px 0 0 0;
    opacity: 0.9;
}

main {
    padding: 30px;
}

#question-number {
    background: #e9ecef;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
    font-weight: bold;
}

#question-text {
    font-size: 1.2em;
    margin-bottom: 20px;
    line-height: 1.5;
}

.answer-option {
    margin: 10px 0;
    padding: 15px;
    border: 2px solid #e9ecef;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.answer-option:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.answer-option input[type="radio"] {
    margin-right: 10px;
}

.navigation {
    margin-top: 30px;
    text-align: center;
}

.navigation button {
    padding: 12px 24px;
    margin: 0 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
}

#prev-btn {
    background: #6c757d;
    color: white;
}

#next-btn, #submit-btn {
    background: #007bff;
    color: white;
}

.start-btn, .restart-btn {
    background: #28a745;
    color: white;
    padding: 15px 30px;
    border: none;
    border-radius: 4px;
    font-size: 18px;
    cursor: pointer;
    display: block;
    margin: 20px auto;
}

.navigation button:hover, .start-btn:hover, .restart-btn:hover {
    opacity: 0.8;
}

#results-screen {
    text-align: center;
}

#score-display {
    font-size: 1.2em;
    margin: 20px 0;
}

.success {
    color: #28a745;
    font-weight: bold;
}

.failure {
    color: #dc3545;
    font-weight: bold;
}

#start-screen {
    text-align: center;
    padding: 40px;
}
';

        file_put_contents($tempDir . '/styles.css', $cssContent);
    }

    private function generateScormApi(string $tempDir): void
    {
        $scormApiContent = '
// API SCORM 2004 simplifiée
const scormAPI = {
    initialized: false,
    data: {},

    initialize: function() {
        this.initialized = true;
        this.data["cmi.core.lesson_status"] = "not attempted";
        this.data["cmi.core.score.raw"] = "0";
        this.data["cmi.core.score.max"] = "100";
        return "true";
    },

    getValue: function(element) {
        return this.data[element] || "";
    },

    setValue: function(element, value) {
        this.data[element] = value;
        return "true";
    },

    commit: function() {
        // Dans un vrai environnement SCORM, ceci communiquerait avec le LMS
        console.log("SCORM Data committed:", this.data);
        return "true";
    },

    finish: function() {
        this.commit();
        this.initialized = false;
        return "true";
    },

    getLastError: function() {
        return "0";
    },

    getErrorString: function(errorCode) {
        return "";
    },

    getDiagnostic: function(errorCode) {
        return "";
    }
};

// Recherche de l\'API SCORM dans la hiérarchie des fenêtres
function findAPI(win) {
    if (win.API_1484_11) {
        return win.API_1484_11;
    }
    if (win.parent && win.parent != win) {
        return findAPI(win.parent);
    }
    return null;
}

// Initialisation de l\'API
let API = findAPI(window);
if (!API) {
    // Utiliser notre API de fallback si aucune API SCORM n\'est trouvée
    window.API_1484_11 = scormAPI;
    API = scormAPI;
}
';

        file_put_contents($tempDir . '/scorm_api.js', $scormApiContent);
    }

    private function createZipArchive(Quizz $quizz, string $tempDir): string
    {
        $zipPath = sys_get_temp_dir() . '/quiz_' . $quizz->getId() . '_scorm_' . time() . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create ZIP archive');
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($tempDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return $zipPath;
    }

    private function cleanupTempDir(string $tempDir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($tempDir);
    }
}
