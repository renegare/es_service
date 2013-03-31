<?php
    
    namespace Renegare\ES\Tests\Mock;

    use Renegare\ES\DataSourceInterface;
    use Renegare\ES\LastSyncHandlerInterface;

    class TestModel implements DataSourceInterface, LastSyncHandlerInterface {

        // DataSourceInterface stubs
        public function getData( $index, $doc_type, \DateTime $last_sync = null ) { }

        // LastSyncHandlerInterface stubs
        public function getLastSync( $index = null, $doc_type = null ) { }
        public function setLastSync( $index = null, $doc_type = null ) { }
    }