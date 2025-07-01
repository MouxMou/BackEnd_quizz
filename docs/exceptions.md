# 🚨 **Système de Gestion d'Exceptions - API Quiz**

Ce document décrit le système complet de gestion d'erreurs mis en place pour l'API Quiz.

## **📋 Vue d'Ensemble**

Le système d'exceptions inclut :
- **Gestionnaire global d'exceptions** (`ExceptionSubscriber`)
- **Exceptions personnalisées** pour les erreurs métier
- **Trait helper** pour faciliter l'utilisation dans les contrôleurs
- **Réponses JSON standardisées** avec codes d'erreur appropriés
- **Logging automatique** des erreurs
- **Gestion différenciée** dev/production

## **🏗️ Architecture**

### **1. ExceptionSubscriber**
Gestionnaire global qui intercepte toutes les exceptions et les transforme en réponses JSON standardisées.

**Fonctionnalités :**
- ✅ Codes de statut HTTP appropriés
- ✅ Messages sécurisés en production
- ✅ Détails de debug en développement
- ✅ Logging automatique avec niveaux appropriés
- ✅ Gestion spécialisée par type d'exception

### **2. Exceptions Personnalisées**

| Exception | Code HTTP | Usage |
|-----------|-----------|-------|
| `QuizNotFoundException` | 404 | Quiz introuvable |
| `QuestionNotFoundException` | 404 | Question introuvable |
| `QuizValidationException` | 422 | Erreurs de validation |
| `InsufficientPermissionsException` | 403 | Permissions insuffisantes |
| `BusinessLogicException` | 400 | Règles métier violées |
| `RateLimitExceededException` | 429 | Limite de taux dépassée |
| `InvalidQuizStateException` | 400 | État de quiz invalide |

### **3. ExceptionHelperTrait**
Trait fournissant des méthodes helper pour lancer facilement les exceptions personnalisées.

## **📖 Guide d'Utilisation**

### **Dans un Contrôleur**

```php
<?php

namespace App\Controller;

use App\Traits\ExceptionHelperTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MyController extends AbstractController
{
    use ExceptionHelperTrait;

    public function getQuiz(int $id): JsonResponse
    {
        $quiz = $this->quizRepository->find($id);
        
        // Au lieu de retourner une JsonResponse d'erreur
        if (!$quiz) {
            $this->throwQuizNotFound($id);
        }
        
        return new JsonResponse($quiz);
    }
    
    public function updateQuiz(int $id, Request $request): JsonResponse
    {
        $quiz = $this->quizRepository->find($id);
        $this->assertQuizExists($quiz, $id);
        
        // Vérifier les permissions
        $this->assertUserPermission(
            $this->isGranted('QUIZ_EDIT', $quiz),
            'quiz',
            'edit'
        );
        
        // Vérifier les règles métier
        $this->assertBusinessRule(
            $quiz->getStatus() !== 'archived',
            'Cannot edit archived quiz'
        );
        
        // ... logique de mise à jour
    }
}
```

### **Méthodes Helper Disponibles**

```php
// Lancer des exceptions
$this->throwQuizNotFound(123);
$this->throwQuestionNotFound(456);
$this->throwQuizValidation(['name' => 'Required']);
$this->throwInsufficientPermissions('quiz', 'delete');
$this->throwBusinessLogic('Custom message');
$this->throwRateLimitExceeded(60);
$this->throwInvalidQuizState('draft', 'published');

// Assertions (lancent une exception si la condition échoue)
$this->assertQuizExists($quiz, $id);
$this->assertQuestionExists($question, $id);
$this->assertUserPermission($hasPermission, 'resource', 'action');
$this->assertBusinessRule($isValid, 'Error message');
```

## **📄 Format des Réponses d'Erreur**

### **En Production**
```json
{
  "error": {
    "type": "QUIZ_NOT_FOUND",
    "message": "Resource not found",
    "code": 404,
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

### **En Développement**
```json
{
  "error": {
    "type": "QUIZ_NOT_FOUND",
    "message": "Quiz with ID 123 not found",
    "code": 404,
    "timestamp": "2024-01-15T10:30:00Z"
  },
  "debug": {
    "file": "/app/src/Controller/QuizController.php",
    "line": 45,
    "trace": "...",
    "request_uri": "/api/v1/quiz/123",
    "method": "GET"
  }
}
```

### **Avec Erreurs de Validation**
```json
{
  "error": {
    "type": "QUIZ_VALIDATION_ERROR",
    "message": "Quiz validation failed",
    "code": 422,
    "timestamp": "2024-01-15T10:30:00Z",
    "validation_errors": [
      {
        "field": "name",
        "message": "Name is required",
        "invalid_value": null
      }
    ]
  }
}
```

## **🔍 Tests et Debugging**

### **Endpoint de Test (Développement)**
Un contrôleur de test est disponible pour tester tous les types d'exceptions :

```bash
GET /api/test/exceptions/list
GET /api/test/exceptions/quiz-not-found
GET /api/test/exceptions/validation-error
# ... etc
```

⚠️ **Important :** Supprimer `TestExceptionController` en production !

### **Logs**
Les exceptions sont automatiquement loggées avec des niveaux appropriés :
- `error` : Erreurs internes (500)
- `warning` : Problèmes d'autorisation (401, 403)
- `notice` : Erreurs de validation (400, 422)
- `info` : Ressources non trouvées (404)

## **⚙️ Configuration**

### **Services (config/services.yaml)**
```yaml
App\EventSubscriber\ExceptionSubscriber:
    arguments:
        $environment: '%kernel.environment%'
```

### **Variables d'Environnement**
- `APP_ENV=dev` : Mode développement (détails complets)
- `APP_ENV=prod` : Mode production (messages sécurisés)

## **✅ Bonnes Pratiques**

### **✅ À Faire**
- Utiliser les exceptions personnalisées pour les erreurs métier
- Utiliser les méthodes `assert*` pour les vérifications
- Laisser le système gérer automatiquement les réponses
- Logguer les informations contextuelle importantes

### **❌ À Éviter**
- Retourner manuellement des `JsonResponse` d'erreur
- Exposer des détails sensibles en production
- Ignorer les exceptions sans les traiter
- Créer des exceptions pour des cas non-exceptionnels

## **🚀 Exemples Concrets**

### **Créer un Quiz**
```php
public function createQuiz(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    
    // Validation basique
    if (!$data['name']) {
        $this->throwQuizValidation([
            ['field' => 'name', 'message' => 'Name is required']
        ]);
    }
    
    // Vérifier les permissions
    $this->assertUserPermission(
        $this->isGranted('QUIZ_CREATE'),
        'quiz',
        'create'
    );
    
    // Règle métier
    $this->assertBusinessRule(
        $this->userQuizCount() < 10,
        'Maximum 10 quizzes per user'
    );
    
    // ... création du quiz
}
```

### **Supprimer un Quiz**
```php
public function deleteQuiz(int $id): JsonResponse
{
    $quiz = $this->quizRepository->find($id);
    $this->assertQuizExists($quiz, $id);
    
    $this->assertUserPermission(
        $this->isGranted('QUIZ_DELETE', $quiz),
        'quiz',
        'delete'
    );
    
    $this->assertBusinessRule(
        !$quiz->hasActiveParticipants(),
        'Cannot delete quiz with active participants'
    );
    
    $this->entityManager->remove($quiz);
    $this->entityManager->flush();
    
    return new JsonResponse(null, Response::HTTP_NO_CONTENT);
}
```

## **📊 Monitoring**

Le système d'exceptions facilite le monitoring :
- **Logs structurés** avec contexte de requête
- **Codes d'erreur standardisés** pour les métriques
- **Types d'erreur catégorisés** pour l'analyse
- **Timestamps précis** pour le debugging

---

**🎯 Résultat :** Une API robuste avec une gestion d'erreurs professionnelle, sécurisée et facile à déboguer ! 
