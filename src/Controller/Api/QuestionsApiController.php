<?php

namespace App\Controller\Api;

use App\Entity\Questions;
use App\Repository\ChoicesRepository;
use App\Service\QuestionFinderService;
use App\Service\QuestionFormatterService;
use App\Service\QuestionLimitService;
use App\Service\ScoresService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_question_')]
class QuestionsApiController extends AbstractController
{
    #[Route('/question', name: 'get_random_question', methods: ['GET'])]
    public function getRandomQuestion(
        Request                  $request,
        QuestionFinderService    $finderService,
        QuestionFormatterService $formatterService,
        QuestionLimitService     $limitService
    ): JsonResponse
    {
        $difficultyLevel = $request->query->get('difficulty') ?? null;
        $categories = $request->query->all('category');

        $question = $finderService->findRandomQuestion($difficultyLevel, $categories);

        if (!$question) {
            return new JsonResponse('No question found.', 404);
        }

        $questionData = $formatterService->formatQuestionData($question);

        if ($this->getUser() === null) {
            if ($limitService->isLimitReached($request)) {
                return $limitService->getLimitResponse();
            }

            return $limitService->createResponseWithCookie($questionData, $request);
        }

        return $this->json($questionData);
    }

    #[Route('/question/{id}', name: 'get_question', methods: ['GET'])]
    public function getQuestion(
        QuestionFormatterService $formatterService,
        ChoicesRepository        $choicesRepository,
        Questions                $question
    ): JsonResponse
    {
        $questionData = $formatterService->formatQuestionData($question);
        $correctChoices = $choicesRepository->findCorrectAnswerIdsByQuestionId($question->getId());
        $questionData['correctChoices'] = $correctChoices;

        return $this->json($questionData);
    }

    #[Route('/question/{id}/check', name: 'check_answers', methods: ['POST'])]
    public function checkAnswers(
        ScoresService     $scoresService,
        ChoicesRepository $choicesRepository,
        Request           $request,
        Questions         $question
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $answers = $data['answers'] ?? null;
        $user = $this->getUser();

        if ($answers === null) {
            return $this->json(['error' => 'No answer provided'], 400);
        }

        $correctChoices = $choicesRepository->findCorrectAnswerIdsByQuestionId($question->getId());
        $diff1 = array_diff($correctChoices, $answers);
        $diff2 = array_diff($answers, $correctChoices);
        $match = (empty($diff1) && empty($diff2));

        if ($user && $match) {
            $scoresService->setScores($user, $question->getDifficulty());
        }

        return $this->json([
            'correctChoices' => $correctChoices,
            'isMatch' => $match
        ]);
    }
}