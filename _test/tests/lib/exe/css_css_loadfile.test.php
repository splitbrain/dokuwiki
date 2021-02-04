<?php

require_once DOKU_INC.'lib/exe/css.php';

class css_css_loadfile_test extends DokuWikiTest {

    protected $file = '';

    public function setUp() : void {
        $this->file = tempnam(TMP_DIR, 'css');
    }

    private function csstest($input, $output = null, $location = 'http://www.example.com/') {
        io_saveFile($this->file, $input);
        $this->assertEquals((is_null($output) ? $input : $output), css_loadfile($this->file, $location));
    }

    public function test_url_relative() {
        $this->csstest('#test { background: url("test/test.png"); }', '#test { background: url("http://www.example.com/test/test.png"); }');
        $this->csstest('#test { background: url(\'test/test.png\'); }', '#test { background: url(\'http://www.example.com/test/test.png\'); }');
    }

    public function test_url_absolute() {
        $this->csstest('#test { background: url("/test/test.png"); }');
        $this->csstest('#test { background: url(\'/test/test.png\'); }');
    }

    public function test_url_with_protocol() {
        $this->csstest('#test { background: url("http://www.test.com/test/test.png"); }');
        $this->csstest('#test { background: url("https://www.test.com/test/test.png"); }');
        $this->csstest('#test { background: url(\'http://www.test.com/test/test.png\'); }');
        $this->csstest('#test { background: url(\'https://www.test.com/test/test.png\'); }');
    }

    public function test_import_relative() {
        $this->csstest('@import "test/test.png";', '@import "http://www.example.com/test/test.png";');
        $this->csstest('@import \'test/test.png\';', '@import \'http://www.example.com/test/test.png\';');
        $this->csstest('@import url(test/test.png);', '@import url(http://www.example.com/test/test.png);');
        $this->csstest('@import url("test/test.png");', '@import url("http://www.example.com/test/test.png");');
    }

    public function test_import_absolute() {
        $this->csstest('@import "/test/test.png";');
        $this->csstest('@import \'/test/test.png\';');
        $this->csstest('@import url(/test/test.png);');
        $this->csstest('@import url("/test/test.png");');
    }

    public function test_import_with_protocol() {
        $this->csstest('@import "http://www.test.com/test/test.png";');
        $this->csstest('@import "https://www.test.com/test/test.png";');
        $this->csstest('@import \'http://www.test.com/test/test.png\';');
        $this->csstest('@import \'https://www.test.com/test/test.png\';');
        $this->csstest('@import url(http://www.test.com/test/test.png);');
        $this->csstest('@import url("http://www.test.com/test/test.png");');
    }

    public function test_less_basic() {
        $this->csstest('@import "test.less"', '@import "/test.less"');
        $this->csstest('@import "/test.less"', '@import "/test.less"');
        $this->csstest('@import "foo/test.less"', '@import "/foo/test.less"');
        $this->csstest('@import url(http://test.less)');
    }

    // more expected use, where less @import(ed) from e.g. lib/plugins/plugin_name
    public function test_less_subdirectories() {

        unlink($this->file);

        $dir = TMP_DIR.'/foo/bar';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!is_dir($dir)) {
            $this->markTestSkipped('Could not create directory.');
        }

        $this->file = tempnam($dir, 'css');

        if (isWindows()) {
            $this->csstest('@import "test.less"', '@import "\foo\bar/test.less"');
            $this->csstest('@import \'test.less\'', '@import \'\foo\bar/test.less\'');
            $this->csstest('@import url(test.less)', '@import url(\foo\bar/test.less)');
            $this->csstest('@import "abc/test.less"', '@import "\foo\bar/abc/test.less"');
        } else {
            $this->csstest('@import "test.less"', '@import "/foo/bar/test.less"');
            $this->csstest('@import \'test.less\'', '@import \'/foo/bar/test.less\'');
            $this->csstest('@import url(test.less)', '@import url(/foo/bar/test.less)');
            $this->csstest('@import "abc/test.less"', '@import "/foo/bar/abc/test.less"');
        }
    }

    public function tearDown() : void {
        unlink($this->file);
        unset($this->file);
    }
}

//Setup VIM: ex: et ts=4 sw=4 :
