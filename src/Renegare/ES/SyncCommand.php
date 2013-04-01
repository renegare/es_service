<?php

    namespace Renegare\ES;
    
    use Symfony\Component\Console\Command\Command as SymfonyCommand;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;

    class SyncCommand extends SymfonyCommand
    {

        protected $manager;

        public function setContainer( \Pimple $app )
        {
            $this->app = $app;
        }
        protected function configure()
        {
            $this
                ->setName('es:sync')
                ->setDescription('Sync db based index with elasticsearch. Full Sync goes through all documents in ES and ensures they exist in the db else removes or adds. Delta goes through all the records in the db and makes updates only where the modified date is greater than the time of the last sync')
                ->setDefinition(array(
                    new InputArgument('index', InputArgument::REQUIRED, 'Index name'),
                    new InputArgument('type', InputArgument::OPTIONAL, 'Document type name'),
                    new InputOption('sync', 's', InputOption::VALUE_REQUIRED, 'Synchronisation type', Manager::SYNC_FULL),
                    new InputOption('reset', 'r', InputOption::VALUE_NONE, 'Reset Index (a.k.a delete it if it exists and create from scratch)'),
                ))

            ;
        }

        public function setManager( $manager ) {
            $this->manager = $manager;
        }

        protected function execute(InputInterface $input, OutputInterface $output)
        {
            $method = $input->getOption('sync');

            if( !in_array( $method, array( Manager::SYNC_FULL, Manager::SYNC_DELTA))) {
                throw new CommandException( sprintf("You can only run a sync of one of the following types: %s, %s", Manager::SYNC_FULL, Manager::SYNC_DELTA ) );
            }

            if( $method != Manager::SYNC_FULL && $input->getOption('reset') ) {
                // $this->manager->resetIndex( $input->getArgument('index') );
            }

            $this->manager->sync( $method, $input->getArgument('index'), $input->hasArgument('type')? $input->getArgument('type') : '' );

            $output->writeln( 'Sync Completed!' );

        }
    }
