<?php

    namespace Renegare\ES;

    interface LastSyncHandlerInterface {
        public function getLastSync( $index = null, $doc_type = null );
        public function setLastSync( \DateTime $last_sync, $index = null, $doc_type = null );
    }