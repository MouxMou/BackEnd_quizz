<?php

namespace App\Command;

use App\Repository\QuizzRepository;
use App\Service\QuizCacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'quiz:cache:warmup',
    description: 'Warm up quiz cache with popular content'
)]
class CacheWarmupCommand extends Command
{
    public function __construct(
        private QuizCacheService $cacheService,
        private QuizzRepository $quizzRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Number of quizzes to warm up', 20)
            ->addOption('status', 's', InputOption::VALUE_OPTIONAL, 'Quiz status to warm up', 'active')
            ->setHelp('This command pre-loads popular quizzes into cache for better performance');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = (int) $input->getOption('limit');
        $status = $input->getOption('status');

        $io->title('Quiz Cache Warmup');

        // Get quizzes to warm up
        $criteria = $status ? ['status' => $status] : [];
        $quizzes = $this->quizzRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC'],
            $limit
        );

        if (empty($quizzes)) {
            $io->warning('No quizzes found to warm up');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Warming up cache for %d quizzes...', count($quizzes)));

        // Create progress bar
        $progressBar = new ProgressBar($output, count($quizzes));
        $progressBar->start();

        $warmedUp = [];
        $errors = [];

        foreach ($quizzes as $quiz) {
            try {
                // Warm up different cache types for each quiz
                $this->cacheService->cacheQuiz($quiz);

                // Also warm up the questions separately
                $this->cacheService->getQuizQuestions($quiz->getId());

                $warmedUp[] = [
                    'id' => $quiz->getId(),
                    'name' => $quiz->getName(),
                    'questions_count' => $quiz->getQuestions()->count()
                ];

                $progressBar->advance();

            } catch (\Exception $e) {
                $errors[] = [
                    'quiz_id' => $quiz->getId(),
                    'error' => $e->getMessage()
                ];
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $output->writeln(''); // New line after progress bar

        // Warm up common list caches
        $io->text('Warming up list caches...');

        try {
            // Warm up popular lists
            $this->cacheService->getAllQuizzes(['status' => 'active'], ['createdAt' => 'DESC'], 10);
            $this->cacheService->getAllQuizzes([], ['createdAt' => 'DESC'], 20);

            $io->success('List caches warmed up');
        } catch (\Exception $e) {
            $io->error('Failed to warm up list caches: ' . $e->getMessage());
        }

        // Display results
        $io->success(sprintf(
            'Cache warmup completed! %d quizzes cached successfully.',
            count($warmedUp)
        ));

        if (!empty($errors)) {
            $io->warning(sprintf('%d errors occurred during warmup:', count($errors)));
            foreach ($errors as $error) {
                $io->text(sprintf('Quiz ID %d: %s', $error['quiz_id'], $error['error']));
            }
        }

        // Display statistics
        if ($io->isVerbose()) {
            $io->section('Cached Quizzes');
            foreach ($warmedUp as $quiz) {
                $io->text(sprintf(
                    'ID: %d | Name: %s | Questions: %d',
                    $quiz['id'],
                    $quiz['name'],
                    $quiz['questions_count']
                ));
            }
        }

        // Show cache stats
        $stats = $this->cacheService->getCacheStats();
        $io->section('Cache Configuration');
        foreach ($stats['cache_strategies'] as $type => $config) {
            $io->text("$type: $config");
        }

        return Command::SUCCESS;
    }
}
