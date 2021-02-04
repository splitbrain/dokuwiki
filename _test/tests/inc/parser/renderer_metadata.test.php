<?php

/**
 * Class renderer_xhtml_test
 */
class renderer_metadata_test extends DokuWikiTest {
    /** @var Doku_Renderer_xhtml */
    protected $R;

    /**
     * Called for each test
     *
     * @throws Exception
     */
    function setUp() : void{
        parent::setUp();
        $this->R = new Doku_Renderer_metadata();
    }

    function tearDown() : void {
        unset($this->R);
    }


    function test_footnote_and_abstract() {
        // avoid issues with the filectime() & filemtime in document_start() & document_end()
        $now = time();
        $this->R->persistent['date']['created'] = $now;
        $this->R->persistent['date']['modified'] = $now;

        $this->R->document_start();

        $this->R->cdata("abstract: ");

        $this->R->footnote_open();
        $this->R->cdata(str_pad("footnote: ", Doku_Renderer_metadata::ABSTRACT_MAX, "lotsa junk "));
        $this->R->footnote_close();

        $this->R->cdata("abstract end.");

        $this->R->document_end();

        $expected = 'abstract: abstract end.';
        $this->assertEquals($expected, $this->R->meta['description']['abstract']);
    }

}
