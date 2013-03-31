<?php

    namespace Renegare\ES;

    use Elastica\Client;

    class Manager {

        const SYNC_FULL = 'full';
        const SYNC_DELTA = 'delta';

        protected $config;
        protected $client;
        protected $indexes;

        public function __construct( $config, DataSourceInterface $data_source = null, LastSyncHandlerInterface $last_sync_handler = null ) {
            // parent::__construct( array( 'servers' => $config['servers'] ) );
            $this->data_source = $data_source;
            $this->last_sync_handler = $last_sync_handler;
            $this->config = $config;
        }

        public function setDataSource( DataSourceInterface $data_source ) {
            $this->data_source = $data_source;
        }

        // @TODO: use an aliases so that full syncs happen on a hidden index and then switched. needs more research!
        public function sync ( $sync_type, $index = '', $type = '' ) {
            $indexes = $index !== ''? array( $this->getIndex( $index, true ) ) : $this->getAllIndexes( true );

            // loop through each index and sync matching doc types from source
            foreach( $indexes as $index ) {

                // get the types we are going to loop through
                if( $sync_type === Manager::SYNC_FULL ) {
                    // delete index and rebuild
                    $index = $this->resetIndex( $index->getName() );
                }

                $mapping = $index->getMapping(); // as $type => 
                foreach( $mapping[ $index->getName() ] as $name => $type_config ) {
                    if( $type === $name || !$type ) {
                        // lets do this!
                        $type_obj = $index->getType( $name );
                        // we have a single data source, pass in the index, type of data we want, last sync DateTime Object and keep it simple :)
                        $data = $this->data_source->getData( $index->getName(), $type, $sync_type === Manager::SYNC_DELTA? $this->getLastSyncDateTime( $index->getName(), $type ) : null );

                        $docs = array();
                        $data_count = count( $data );
                        $count = 0;
                        $batch_size = 100;

                        foreach( $data as $id => $data ) {
                            ++$count;
                            // if the deleted flag is set, we delete it :)
                            if( isset( $data['__deleted'] ) ) {
                                $type_obj->deleteById( $id );
                            } else {
                                $docs[] = new \Elastica\Document( $id, $data );
                            }

                            // when we reach the batch size we push and clean up memory #fingersCrossed
                            if( !($count % $batch_size) || $count === $data_count ) {
                                try {
                                    $type_obj->addDocuments( $docs );
                                    $index->refresh();
                                } catch ( \Elastica\Exception\BulkResponseException $e ) {
                                    throw $e;
                                }
                                $docs = array();
                            }
                        }
                        $this->setLastSyncDateTime( $index->getName(), $type );
                    }
                }
            }
        }

        public function getAllIndexes( $create = false ) {
            if( count( $this->indexes ) != count( $this->config['indexes'] ) ) {
                foreach( $this->config['indexes'] as $name => $config ) {
                    $this->getIndex($name, $create);
                }
            }

            return $this->indexes;
        }

        public function getIndex( $name, $create = false ) {
            if( !isset($this->indexes[$name]) ) {
                if( !isset( $this->config['indexes'][$name] ) ) {
                    throw new \Exception( sprintf("Index %n configuration was not found", $name) );
                }

                $index = $this->getClient()->getIndex($name);
                if ( !$index->exists() ) {
                    if( $create ) {
                        $this->createIndex( $index, $this->config['indexes'][$name]);
                    } else {
                        return null;
                    }
                }

                $this->indexes[$name] = $index;
            }

            return $this->indexes[$name];
        }

        public function createIndex( $index, $config ) {
            $response = $index->create( $config['config'], $config['options'] );
            if( $response->isOk() ) {
                // lets register the mappings
                foreach( $config['mappings'] as $type => $type_config ) {
                    $type = $index->getType( $type );
                    $mapping = new \Elastica\Type\Mapping();
                    $mapping->setType( $type );
                    $mapping->setParam('index_analyzer', 'indexAnalyzer');
                    $mapping->setParam('search_analyzer', 'searchAnalyzer');
                    $mapping->setParam('_boost', array('name' => '_boost', 'null_value' => 1.0));
                    $mapping->setProperties( $type_config['properties'] );
                    $mapping->send();
                }
            }

            return $response;
        }

        public function getClient( ) {
            if( !$this->client ) {
                $this->client = new Client( array( 'servers' => $this->config['servers'] ) );
            }
            return $this->client;
        }

        public function setClient( Client $client ){
            $this->client = $client;
        }

        public function setLastSyncHandler( LastSyncHandlerInterface $last_sync_handler ) {
            $this->last_sync_handler = $last_sync_handler;
        }

        public function getLastSyncDateTime( $index='', $doc_type='' ){
            return $this->last_sync_handler? $this->last_sync_handler->getLastSync( $index, $doc_type ) : null;
        }

        public function setLastSyncDateTime( $index='', $doc_type='' ){
            return $this->last_sync_handler? $this->last_sync_handler->setLastSync( $index, $doc_type ) : null;
        }

        public function resetIndex( $index_name ) {
            if( isset( $this->indexes[$index_name]) ) {
                $this->indexes[$index_name]->delete();
                unset( $this->indexes[$index_name] );
            }
            return $this->getIndex( $index_name, true );
        }

        public function __destruct() {
            // do something good?
        }
    }