<?php

namespace App\BarBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command that outputs "Hi from Bar!".
 */
#[AsCommand(
	name: 'bar:hi',
	description: 'Outputs "Hi from Bar!"'
)]
class BarCommand extends Command
{
	private LoggerInterface $logger;

	/**
	 * BarCommand constructor.
	 *
	 * @param LoggerInterface $logger Logger instance for tracking command execution.
	 */
	public function __construct(LoggerInterface $logger)
	{
		parent::__construct();
		$this->logger = $logger;
	}

	protected function configure(): void
	{
		$this->addOption(
			'from-master',
			null,
			InputOption::VALUE_NONE,
			'Indicates if this command was triggered by foo:hello.'
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		if (!$input->getOption('from-master')) {
			$errorMessage = 'Error: bar:hi command is a member of foo:hello command chain and cannot be executed on its own.';
			$output->writeln($errorMessage);
			$this->logger->error($errorMessage);

			return Command::FAILURE;
		}

		$message = 'Hi from Bar!';
		$output->writeln($message);
		$this->logger->info($message);

		return Command::SUCCESS;
	}
}
