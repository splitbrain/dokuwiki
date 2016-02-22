<?php
namespace plugin\struct\test;

// we don't have the auto loader here
spl_autoload_register(array('action_plugin_struct_autoloader', 'autoloader'));

use plugin\struct\meta\SchemaBuilder;
use plugin\struct\meta\Schema;

/**
 * Tests for the class action_plugin_magicmatcher_oldrevisions of the magicmatcher plugin
 *
 * @group plugin_struct
 * @group plugins
 *
 */
class schemaBuilder_struct_test extends \DokuWikiTest {

    protected $pluginsEnabled = array('struct', 'sqlite');

    /** @var \helper_plugin_sqlite $sqlite */
    protected $sqlite;

    public function setUp() {
        parent::setUp();

        /** @var \helper_plugin_struct_db $sqlite */
        $sqlite = plugin_load('helper', 'struct_db');
        $this->sqlite = $sqlite->getDB();
    }

    public function tearDown() {
        parent::tearDown();

        /** @noinspection SqlResolve */
        $res = $this->sqlite->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tableNames = $this->sqlite->res2arr($res);
        $tableNames = array_map(function ($value) { return $value['name'];},$tableNames);
        $this->sqlite->res_close($res);

        foreach ($tableNames as $tableName) {
            if ($tableName == 'opts') continue;
            $this->sqlite->query('DROP TABLE ?', $tableName);
        }


        $this->sqlite->query("CREATE TABLE schema_assignments ( assign NOT NULL, tbl NOT NULL, PRIMARY KEY(assign, tbl) );");
        $this->sqlite->query("CREATE TABLE schema_cols ( sid INTEGER REFERENCES schemas (id), colref INTEGER NOT NULL, enabled BOOLEAN DEFAULT 1, tid INTEGER REFERENCES types (id), sort INTEGER NOT NULL, PRIMARY KEY ( sid, colref) )");
        $this->sqlite->query("CREATE TABLE schemas ( id INTEGER PRIMARY KEY AUTOINCREMENT, tbl NOT NULL, ts INT NOT NULL, chksum DEFAULT '' )");
        $this->sqlite->query("CREATE TABLE sqlite_sequence(name,seq)");
        $this->sqlite->query("CREATE TABLE types ( id INTEGER PRIMARY KEY AUTOINCREMENT, class NOT NULL, ismulti BOOLEAN DEFAULT 0, label DEFAULT '', config DEFAULT '' )");
        $this->sqlite->query("CREATE TABLE multivals ( tbl NOT NULL, colref INTEGER NOT NULL, pid NOT NULL, rev INTEGER NOT NULL, row INTEGER NOT NULL, value, PRIMARY KEY(tbl, colref, pid, rev, row) )");
    }

    /**
     *
     */
    public function test_build_new() {

        // arrange
        $testdata = array();
        $testdata['new']['new1']['sort'] = 70;
        $testdata['new']['new1']['label'] = 'testcolumn';
        $testdata['new']['new1']['ismulti'] = 0;
        $testdata['new']['new1']['config'] = '{"prefix": "", "postfix": ""}';
        $testdata['new']['new1']['class'] = 'Text';
        $testdata['new']['new2']['sort'] = 40;
        $testdata['new']['new2']['label'] = 'testMulitColumn';
        $testdata['new']['new2']['ismulti'] = 1;
        $testdata['new']['new2']['config'] = '{"prefix": "pre", "postfix": "post"}';
        $testdata['new']['new2']['class'] = 'Text';

        $testname = 'testTable';
        $testname = Schema::cleanTableName($testname);

        // act
        $builder = new SchemaBuilder($testname, $testdata);
        $result = $builder->build();

        /** @noinspection SqlResolve */
        $res = $this->sqlite->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", 'data_' . $testname);
        $tableSQL = $this->sqlite->res2single($res);
        $this->sqlite->res_close($res);
        $expected_tableSQL = "CREATE TABLE data_testtable (
                    pid NOT NULL,
                    rev INTEGER NOT NULL,
                    latest BOOLEAN NOT NULL DEFAULT 0, col1 DEFAULT '', col2 DEFAULT '',
                    PRIMARY KEY(pid, rev)
                )";

        $res = $this->sqlite->query("SELECT * FROM types");
        $actual_types = $this->sqlite->res2arr($res);
        $this->sqlite->res_close($res);
        $expected_types = array(
            array(
                'id' => "1",
                'class' => 'Text',
                'ismulti' => "0",
                'label' => "testcolumn",
                'config' => '{"prefix": "", "postfix": ""}'
            ),
            array(
                'id' => "2",
                'class' => 'Text',
                'ismulti' => "1",
                'label' => "testMulitColumn",
                'config' => '{"prefix": "pre", "postfix": "post"}'
            )
        );

        $res = $this->sqlite->query("SELECT * FROM schema_cols");
        $actual_cols = $this->sqlite->res2arr($res);
        $this->sqlite->res_close($res);
        $expected_cols = array(
            array(
                'sid' => "1",
                'colref' => "1",
                'enabled' => "1",
                'tid' => "1",
                'sort' => "70"
            ),
            array(
                'sid' => "1",
                'colref' => "2",
                'enabled' => "1",
                'tid' => "2",
                'sort' => "40"
            )
        );

        $res = $this->sqlite->query("SELECT * FROM schemas");
        $actual_schema = $this->sqlite->res2row($res);
        $this->sqlite->res_close($res);

        $this->assertSame('1', $result);
        $this->assertEquals($expected_tableSQL, $tableSQL);
        $this->assertEquals($expected_types, $actual_types);
        $this->assertEquals($expected_cols, $actual_cols);
        $this->assertEquals('1', $actual_schema['id']);
        $this->assertEquals($testname, $actual_schema['tbl']);
        $this->assertEquals('', $actual_schema['chksum']);
        $this->assertTrue((int)$actual_schema['ts'] > 0, 'timestamp should be larger than 0');
    }


    public function test_build_update() {

        // arrange
        $initialdata = array();
        $initialdata['new']['new1']['sort'] = 70;
        $initialdata['new']['new1']['label'] = 'testcolumn';
        $initialdata['new']['new1']['ismulti'] = 0;
        $initialdata['new']['new1']['config'] = '{"prefix": "", "postfix": ""}';
        $initialdata['new']['new1']['class'] = 'Text';

        $testname = 'testTable';
        $testname = Schema::cleanTableName($testname);

        $builder = new SchemaBuilder($testname, $initialdata);
        $result = $builder->build();
        $this->assertSame($result, '1', 'Prerequiste setup  in order to have basis which to change during act');

        $updatedata = array();
        $updatedata['id'] = "1";
        $updatedata['cols']['1']['sort'] = 65;
        $updatedata['cols']['1']['label'] = 'testColumn';
        $updatedata['cols']['1']['ismulti'] = 1;
        $updatedata['cols']['1']['config'] = '{"prefix": "pre", "postfix": "fix"}';
        $updatedata['cols']['1']['class'] = 'Text';

        // act
        $builder = new SchemaBuilder($testname, $updatedata);
        $result = $builder->build();

        $res = $this->sqlite->query("SELECT * FROM types");
        $actual_types = $this->sqlite->res2arr($res);
        $this->sqlite->res_close($res);
        $expected_types = array(
            array(
                'id' => "1",
                'class' => 'Text',
                'ismulti' => "0",
                'label' => "testcolumn",
                'config' => '{"prefix": "", "postfix": ""}'
            ),
            array(
                'id' => "2",
                'class' => 'Text',
                'ismulti' => "1",
                'label' => "testColumn",
                'config' => '{"prefix": "pre", "postfix": "fix"}'
            )
        );

        // assert
        $this->assertSame($result, '2');
        $this->assertEquals($actual_types, $expected_types);

    }
}