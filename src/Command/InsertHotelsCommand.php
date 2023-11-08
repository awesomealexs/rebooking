<?php

namespace App\Command;

use App\Handler\HotelHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:insert-hotels',
    description: 'Create main user',
)]
class InsertHotelsCommand extends Command
{
    protected HotelHandler $hotelHandler;
    public function __construct()
    {
        $this->hotelHandler = new HotelHandler();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->hotelHandler->handleHotelsDumpFile();


        return Command::SUCCESS;
    }
}
