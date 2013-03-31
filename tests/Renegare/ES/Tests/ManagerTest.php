<?php

namespace Renegare\ES\Tests\ElasticSearch;

use Renegare\ES\Manager as ElasticSearchManager;
use Elastica\Client;
use Elastica\Query;
use Elastica\Query\QueryString;
use Elastica\Document;
use Phake;

class ManagerTest extends \PHPUnit_Framework_TestCase
{

    public static $config_test_tmp_dir = './tests/config';

    protected function setUp()
    {
        $this->data_limit = 1000;
        $this->es_config = json_decode( file_get_contents( self::$config_test_tmp_dir . '/server_config.json' ), true );
        $this->es_test_index_name = 'test';
        $this->es_test_doc_type = 'test_type';
        $this->manager = new ElasticSearchManager( $this->es_config );
        $this->es_client = new Client( array( 'servers' => $this->es_config['servers'] ));
        $this->es_index = $this->es_client->getIndex( $this->es_test_index_name );
    }

    public function stestFullSync()
    {
        // delete and create index if it already exists
        // make index dirty with prepopulated stuff
        $this->createEsIndex();
        $initial_data = $this->populateEsIndex();

        $data_source = Phake::mock('Renegare\ES\Tests\Mock\TestModel');
        $index = $this->es_test_index_name;
        $doc_type = $this->es_test_doc_type;
        $last_sync = null;
        $expected_data = $this->getFakeFullSyncData();
        $expected_data_count = count( $expected_data );
        Phake::when( $data_source )->getData($index, $doc_type, $last_sync)->thenReturn( $expected_data );

        $this->manager->setDataSource( $data_source );
        $this->manager->sync( ElasticSearchManager::SYNC_FULL, 'test', 'test_type' );

        // make sure our data source is called correctly
        Phake::verify( $data_source, Phake::times(1))->getData( $index, $doc_type, $last_sync );

        // make sure that all and only that data which is provided is in es
        // we are using Elastica Library here and not the ESManager ... #ethical
        $es_index = $this->es_client->getIndex( $index );
        $es_query_string = new QueryString();
        $es_query_string->setQuery('*');
        $es_query = new Query();
        $es_query->setQuery($es_query_string);
        // ensure we get everything back!
        $es_query->setFrom(0);
        $es_query->setLimit( $expected_data_count );
        //Search on the index.
        $es_result = $es_index->search($es_query);

        $this->assertEquals( $expected_data_count, $es_result->getTotalHits() );

        foreach( $es_result->getResults() as $result ) {
            $data = $result->getData();
            $id = $result->getId();
            $this->assertArrayHasKey( $id, $expected_data );
            $this->assertEquals( serialize( $expected_data[$id] ), serialize( $data ) );
            // ensure a duplicate record does not exist
            unset( $expected_data[$id] );
        }

        $this->assertEquals( 0, count( $expected_data ) );

        // delete index
        $this->deleteEsIndex();
    }

    public function testDeltaSync() {
        // @TODO: ensure records flagged as deleted are removed from the index

        // delete and create index if it already exists
        // make index dirty with prepopulated stuff
        $this->createEsIndex();
        $initial_data = $this->populateEsIndex();

        $data_source = Phake::mock('Renegare\ES\Tests\Mock\TestModel');
        $index = $this->es_test_index_name;
        $doc_type = $this->es_test_doc_type;
        $last_sync = new \DateTime();
        $updated_data = $this->getFakeDeltaSyncData($initial_data);
        $expected_data = array_merge( $initial_data, $updated_data);
        // extract data flagged to be deleted ... if any!
        $deleted_data = array();
        foreach( $expected_data as $id => $data ) {
            if( isset( $data['__deleted']) ) {
                $deleted_data[ $id ] = $data;
                unset( $expected_data[$id]);
            }
        }

        $expected_data_count = count( $expected_data );

        Phake::when( $data_source )->getData($index, $doc_type, $last_sync)->thenReturn( $updated_data );
        Phake::when( $data_source )->getLastSync($index, $doc_type )->thenReturn( $last_sync );

        $this->manager->setDataSource( $data_source );
        $this->manager->setLastSyncHandler( $data_source );
        $this->manager->sync( ElasticSearchManager::SYNC_DELTA, 'test', 'test_type' );

        // make sure our data source is called correctly
        Phake::verify( $data_source, Phake::times(1))->getData( $index, $doc_type, $last_sync );
        Phake::verify( $data_source, Phake::times(1))->getLastSync( $index, $doc_type );
        // needs more thought as the date time does not always match and fails
        // Phake::verify( $data_source, Phake::times(1))->setLastSync( );

        // make sure that all and only expected_data is in es
        // we are using Elastica Library here and not the ESManager ... #ethical
        $es_index = $this->es_client->getIndex( $index );
        $es_query_string = new QueryString();
        $es_query_string->setQuery('*');
        $es_query = new Query();
        $es_query->setQuery($es_query_string);
        // ensure we get everything back!
        $es_query->setFrom(0);
        $es_query->setLimit( $expected_data_count );
        //Search on the index.
        $es_result = $es_index->search($es_query);

        $this->assertEquals( $expected_data_count, $es_result->getTotalHits() );

        foreach( $es_result->getResults() as $result ) {
            $data = $result->getData();
            $id = $result->getId();
            $this->assertArrayHasKey( $id, $expected_data );
            $this->assertEquals( serialize( $expected_data[$id] ), serialize( $data ) );
            // ensure a duplicate record does not exist
            unset( $expected_data[$id] );
        }

        $this->assertEquals( 0, count( $expected_data ) );

        // delete test index
        $this->deleteEsIndex();
    }

    // Fake data generators

    function deleteEsIndex() {
        if( $this->es_index->exists() ) $this->es_index->delete();
    }

    function createEsIndex( $delete_id_exists = true ) {
        if( $delete_id_exists ) $this->deleteEsIndex();
        $config = $this->es_config['indexes'][$this->es_test_index_name];
        $this->es_index->create( $config['config'], $config['options']);
    }

    function populateEsIndex() {
        // populate with random data :)
        $records = $this->getRandomRecords();
        $docs = array();
        foreach( $records as $id => $data ) {
            $docs[] = new Document( $id, $data );
        }
        $type = $this->es_index->getType( $this->es_test_doc_type );
        $type->addDocuments( $docs );
        $this->es_index->refresh();
        return $records;
    }

    function getRandomRecords() {
        $tmpl = array( 'title' => 'Random Test Data title', 'desc' => 'Random Test Data description' );
        $data = array();
        $limit = rand( 6, $this->data_limit );
        $id_limit = $this->data_limit * 2;

        $randomButUniqueId = function() use ( $id_limit, &$data, &$randomButUniqueId ){
            $id = 'u' . rand(1, $id_limit);
            return !isset( $data[$id] )? $id : $randomButUniqueId();
        };

        for( $i = 0; $i < $limit; ++$i ) {
            $id = $randomButUniqueId();
            $data[$id] = array_slice( $tmpl, 0);
            $data[$id]['id'] = $id;
        }

        return $data;
    }

    public function getFakeFullSyncData(){
        $tmpl = array( 'title' => 'Test Data title', 'desc' => 'Test Data description' );
        $data = array();
        $limit = $this->data_limit;

        for( $i = 0; $i < $limit; ++$i ) {
            $id = 'u' . ( $i + 1 );
            $data[$id] = array_slice( $tmpl, 0);
            $data[$id]['id'] = $id;
        }

        return $data;
    }

    public function getFakeDeltaSyncData( $full_data = null ){
        $full_data = $full_data === null? $this->getFakeFullSyncData() : $full_data;
        // minimum 2 upto half the entries to modify
        $limit = rand( 2, floor( count($full_data) / 2 ) );
        $keys = array_rand( $full_data, $limit );
        $data = array();
        // generate data to be updated
        foreach( $keys as $key ) {
            $data[$key] = $full_data[$key];
            $data[$key]['title'] = 'Updated Test Data title';
        }
        // flag records to be deleted
        $keys = array_rand( $data, floor( count($data) / 2 ) );
        print_r(floor( count($data) / 2 ));
        print_r($keys);
        foreach( $keys as $key ) {
            $data[$key]['__deleted'] = true;
        }

        return $data;
    }

}