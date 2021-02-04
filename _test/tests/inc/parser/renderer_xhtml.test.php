<?php

use dokuwiki\Parsing\Handler\Lists;

/**
 * Class renderer_xhtml_test
 */
class renderer_xhtml_test extends DokuWikiTest {
    /** @var Doku_Renderer_xhtml */
    protected $R;

    /**
     * Called for each test
     *
     * @throws Exception
     */
    function setUp() : void {
        parent::setUp();
        $this->R = new Doku_Renderer_xhtml();
    }

    function tearDown() : void {
        unset($this->R);
    }

    function test_tableopen_0arg() {
        $this->R->table_open();

        $expected = '<div class="table"><table class="inline">'."\n";
        $this->assertEquals($expected, $this->R->doc);
    }

    function test_tableopen_1arg() {
        $this->R->table_open(4);

        $expected = '<div class="table"><table class="inline">'."\n";
        $this->assertEquals($expected, $this->R->doc);
    }

    function test_tableopen_2arg() {
        $this->R->table_open(4, 4);

        $expected = '<div class="table"><table class="inline">'."\n";
        $this->assertEquals($expected, $this->R->doc);
    }

    function test_tableopen_3arg() {
        $this->R->table_open(4, 4, 100);

        $expected = '<div class="table sectionedit1"><table class="inline">'."\n";
        $this->assertEquals($expected, $this->R->doc);
    }

    function test_tableopen_4arg_str() {
        $this->R->table_open(4, 4, 100, 'feature');

        $expected = '<div class="table feature sectionedit1"><table class="inline">'."\n";
        $this->assertEquals($expected, $this->R->doc);
    }

    function test_tableopen_4arg_arr() {
        $this->R->table_open(4, 4, 100, array('feature', 'test'));

        $expected = '<div class="table feature test sectionedit1"><table class="inline">'."\n";
        $this->assertEquals($expected, $this->R->doc);
    }

    function test_table() {
        $this->R->table_open(null, null, null, 'feature');
        $this->R->tablethead_open();

        $this->R->tablerow_open('item');
        $this->R->tableheader_open();
        $this->R->cdata('header1');
        $this->R->tableheader_close();
        $this->R->tableheader_open(1, null, 1, 'second');
        $this->R->cdata('header2');
        $this->R->tableheader_close();
        $this->R->tablerow_close();

        $this->R->tablethead_close();
        $this->R->tabletbody_open();

        $this->R->tablerow_open('values');
        $this->R->tablecell_open(1, null, 1, 'first value');
        $this->R->cdata('cell1,1');
        $this->R->tablecell_close();
        $this->R->tablecell_open(1, null, 1, 'second');
        $this->R->cdata('cell1,2');
        $this->R->tablecell_close();
        $this->R->tablerow_close();

        $this->R->tablerow_open();
        $this->R->tablecell_open();
        $this->R->cdata('cell2.1');
        $this->R->tablecell_close();
        $this->R->tablecell_open();
        $this->R->cdata('cell2,2');
        $this->R->tablecell_close();
        $this->R->tablerow_close();

        $this->R->tabletbody_close();
        $this->R->table_close();

        $expected = '<div class="table feature"><table class="inline">
	<thead>
	<tr class="row0 item">
		<th class="col0">header1</th><th class="col1 second">header2</th>
	</tr>
	</thead>
	<tbody>
	<tr class="row1 values">
		<td class="col0 first value">cell1,1</td><td class="col1 second">cell1,2</td>
	</tr>
	<tr class="row2">
		<td class="col0">cell2.1</td><td class="col1">cell2,2</td>
	</tr>
	</tbody>
</table></div>
';
        $this->assertEquals($expected, $this->R->doc);
    }

    function test_olist() {
        $this->R->document_start();
        $this->R->listo_open();

        $this->R->listitem_open(1, Lists::NODE);
        $this->R->listcontent_open();
        $this->R->cdata('item1');
        $this->R->listcontent_close();

        $this->R->listo_open();

        $this->R->listitem_open(2);
        $this->R->listcontent_open();
        $this->R->cdata('item1b');
        $this->R->listcontent_close();
        $this->R->listitem_close();

        $this->R->listo_close();
        $this->R->listitem_close();

        $this->R->listitem_open(1);
        $this->R->listcontent_open();
        $this->R->cdata('item2');
        $this->R->listcontent_close();
        $this->R->listitem_close();

        $this->R->listitem_open(1, Lists::NODE);
        $this->R->listcontent_open();
        $this->R->cdata('item3');
        $this->R->listcontent_close();

        $this->R->listo_open('special');

        $this->R->listitem_open(2);
        $this->R->listcontent_open();
        $this->R->cdata('item3b');
        $this->R->listcontent_close();
        $this->R->listitem_close();

        $this->R->listo_close();
        $this->R->listitem_close();

        $this->R->listo_close();
        $this->R->document_end();

        $expected = '<ol>
<li class="level1 node"><div class="li">item1</div>
<ol>
<li class="level2"><div class="li">item1b</div>
</li>
</ol>
</li>
<li class="level1"><div class="li">item2</div>
</li>
<li class="level1 node"><div class="li">item3</div>
<ol class="special">
<li class="level2"><div class="li">item3b</div>
</li>
</ol>
</li>
</ol>
';
        $this->assertEquals($expected, $this->R->doc);
    }

    function test_ulist() {
        $this->R->document_start();
        $this->R->listu_open();

        $this->R->listitem_open(1, Lists::NODE);
        $this->R->listcontent_open();
        $this->R->cdata('item1');
        $this->R->listcontent_close();

        $this->R->listu_open();

        $this->R->listitem_open(2);
        $this->R->listcontent_open();
        $this->R->cdata('item1b');
        $this->R->listcontent_close();
        $this->R->listitem_close();

        $this->R->listu_close();
        $this->R->listitem_close();

        $this->R->listitem_open(1);
        $this->R->listcontent_open();
        $this->R->cdata('item2');
        $this->R->listcontent_close();
        $this->R->listitem_close();

        $this->R->listitem_open(1, Lists::NODE);
        $this->R->listcontent_open();
        $this->R->cdata('item3');
        $this->R->listcontent_close();

        $this->R->listu_open('special');

        $this->R->listitem_open(2);
        $this->R->listcontent_open();
        $this->R->cdata('item3b');
        $this->R->listcontent_close();
        $this->R->listitem_close();

        $this->R->listu_close();
        $this->R->listitem_close();

        $this->R->listu_close();
        $this->R->document_end();

        $expected = '<ul>
<li class="level1 node"><div class="li">item1</div>
<ul>
<li class="level2"><div class="li">item1b</div>
</li>
</ul>
</li>
<li class="level1"><div class="li">item2</div>
</li>
<li class="level1 node"><div class="li">item3</div>
<ul class="special">
<li class="level2"><div class="li">item3b</div>
</li>
</ul>
</li>
</ul>
';
        $this->assertEquals($expected, $this->R->doc);
    }

    public function test_blankHeader() {
        $this->R->header('0', 1, 1);
        $expected = '<h1 class="sectionedit1" id="section0">0</h1>';
        $this->assertEquals($expected, trim($this->R->doc));
    }

    public function test_blankTitleLink() {
        global $conf;
        $id = 'blanktest';

        $conf['useheading'] = 1;
        saveWikiText($id,'====== 0 ======', 'test');
        $this->assertTrue(page_exists($id));

        $header = p_get_first_heading($id, METADATA_RENDER_UNLIMITED);
        $this->assertSame('0', $header);

        $this->R->internallink($id);
        $expected = '<a href="/./doku.php?id='.$id.'" class="wikilink1" title="'.$id.'" data-wiki-id="blanktest">0</a>';
        $this->assertEquals($expected, trim($this->R->doc));
    }
}
