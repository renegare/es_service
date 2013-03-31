<?php

    namespace Renegare\ES;

    interface LastSyncHandlerInterface {
        public function getLastSync( $index = null, $doc_type = null );
        public function setLastSync( $index = null, $doc_type = null );
    }