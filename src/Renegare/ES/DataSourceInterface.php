<?php

    namespace Renegare\ES;

    interface DataSourceInterface {
        public function getData( $index, $doc_type, \DateTime $last_sync = null );
    }