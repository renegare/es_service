<?php

namespace Renegare\ES\Tests\ElasticSearch;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Renegare\ES\Command as ElasticSearchCommand;
use Renegare\ES\Manager as ElasticSearchManager;
use Phake;

class SyncCommandTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $application = new Application;
        $command = new ElasticSearchCommand;

        // setup moke objects
        $es_manger = Phake::mock('Renegare\ES\Manager');
        $command->setManager( $es_manger );

        $application->add( $command );

        // expect to invoke full sync
        $this->commandTester = new CommandTester($command);
        $this->command = $command;
        $this->mock_es_manger = $es_manger;
    }

    public function testExecute()
    {   
        $command_name = $this->command->getName();
        $test_index = 'test';
        $doc_type = 'doc_type';
        $cases = array(
            array( 'command' => $command_name, 'index' => $test_index, 'type' => $doc_type ),
            array( 'command' => $command_name, 'index' => $test_index, '--sync' => ElasticSearchManager::SYNC_FULL ),
            array( 'command' => $command_name, 'index' => $test_index, '--sync' => ElasticSearchManager::SYNC_DELTA ),
            array( 'command' => $command_name, 'index' => $test_index )
        );

        foreach( $cases as $case ) {
            $this->commandTester->execute( $case );
        }

        Phake::verify($this->mock_es_manger, Phake::times(2))->sync( ElasticSearchManager::SYNC_FULL, $test_index, null);
        Phake::verify($this->mock_es_manger, Phake::times(1))->sync( ElasticSearchManager::SYNC_DELTA, $test_index, null );
        Phake::verify($this->mock_es_manger, Phake::times(1))->sync( ElasticSearchManager::SYNC_FULL, $test_index, $doc_type);
    }

    /**
     * @expectedException Renegare\ES\CommandException
     */
    public function testInvalidSyncTypeException(){
        $this->commandTester->execute(array(
                'command' => $this->command->getName(),
                'index' => 'test',
                '--sync' => 'typo!!'
        ));
    }
}