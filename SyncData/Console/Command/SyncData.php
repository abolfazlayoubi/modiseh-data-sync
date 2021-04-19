<?php


namespace Modiseh\SyncData\Console\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Modiseh\SyncData\Model\ImportData;
/**
 * Class SomeCommand
 */
class SyncData extends Command
{
    const NAME = 'name';


    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('modiseh:data:sync');
        $this->setDescription('modiseh sync data.');
        $this->addOption(
            self::NAME,
            null,
            InputOption::VALUE_REQUIRED,
            'Name'
        );

        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return null|int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
       try{
           $output->writeln('<info>Start Import.</info>');
           $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
           $objectManager->Create('\Modiseh\SyncData\Model\ImportData')->execute($input->getOption('name'));
           $output->writeln('<comment>Some Comment.</comment>');
       }catch (\Exception $e){
           $output->writeln('<error>'.$e->getMessage().'</error>');
       }
    }
}