<?php

require_once DOKU_INC.'lib/exe/css.php';

class css_at_import_less_test extends DokuWikiTest {

    protected $file = '';
    protected $import = '';

    public function setUpFiles($subdir = '') {

        $dir = TMP_DIR . $subdir;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!is_dir($dir)) {
            $this->markTestSkipped('Could not create directory.');
        }

        $this->file = tempnam($dir, 'css');

        $import = '';
        do {
            if ($import) unlink($import);
            $import = tempnam($dir, 'less');
            $ok = rename($import, $import.'.less');
        } while (!$ok);

        $this->import = $import.'.less';
    }

    private function csstest($input, $expected_css, $expected_less) {
        $location = "http://test.com/";
        io_saveFile($this->file, $input);
        $css = css_loadfile($this->file, $location);
        $less = css_parseless($css);
        $this->assertEquals($expected_css, $css);
        $this->assertEquals($expected_less, $less);
    }

    /**
     * makes proper relative path to be used in CSS @import
     * @param string $path
     * @return string
     */
    private function importPath($path) {
        if (isWindows()) {
            return preg_replace('#(^.*[\\\\])#','', $path);
        }
        return preg_replace('#(^.*[/])#','', $path);
    }

    public function test_basic() {
        $this->setUpFiles();

        $import = $this->importPath($this->import);
        $in_css = '@import "'.$import.'";';
        $in_less = '@foo: "bar";
content: @foo;';

        $expected_css = '@import "/'.$import.'";';
        $expected_less = 'content: "bar";';

        io_saveFile($this->import, $in_less);
        $this->csstest($in_css, $expected_css, $expected_less);
    }

    public function test_subdirectory() {
        $this->setUpFiles('/foo/bar');

        $import = $this->importPath($this->import);
        $in_css = '@import "'.$import.'";';
        $in_less = '@foo: "bar";
content: @foo;';

        $expected_css = isWindows() ? '@import "\\foo\\bar/'.$import.'";' : '@import "/foo/bar/'.$import.'";';
        $expected_less = 'content: "bar";';

        io_saveFile($this->import, $in_less);
        $this->csstest($in_css, $expected_css, $expected_less);
    }

    public function tearDown() : void {
        unlink($this->file);
        unlink($this->import);
        unset($this->file, $this->import);
    }
}

//Setup VIM: ex: et ts=4 sw=4 :
