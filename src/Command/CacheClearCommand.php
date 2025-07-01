<?php

namespace App\Command;

use App\Service\QuizCacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'quiz:cache:clear',
    description: 'Clear quiz cache'
)]
class CacheClearCommand extends Command
{
    public function __construct(
        private QuizCacheService $cacheService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('quiz-id', null, InputOption::VALUE_OPTIONAL, 'Clear cache for specific quiz ID')
            ->setHelp('This command allows you to clear the quiz cache completely or for a specific quiz');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $quizId = $input->getOption('quiz-id');

        if ($quizId) {
            // Clear cache for specific quiz
            $this->cacheService->invalidateQuizCache((int) $quizId);
            $io->success("Cache cleared for quiz ID: {$quizId}");
        } else {
            // Clear all quiz list caches
            $this->cacheService->invalidateListCaches();
            $io->success('All quiz list caches cleared successfully!');

            $io->note('Individual quiz caches are kept. Use --quiz-id to clear specific quiz cache.');
        }

        return Command::SUCCESS;
    }
}
