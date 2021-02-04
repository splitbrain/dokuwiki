<?php

class media_searchlist_test extends DokuWikiTest {

    /**
     * @var string namespace used for testing
     */
    protected $upload_ns = 'media_searchlist_test';

    /**
     * Save the file
     *
     * @param $name name of saving file
     * @param $copy file used as a content of uploaded file
     */
    protected function save($name, $copy) {
        $media_id = $this->upload_ns.':'.$name;
        media_save(array('name' => $copy), $media_id, true, AUTH_UPLOAD, 'copy');
    }

    /**
     * Called for each test
     *
     * @throws Exception
     */
    function setUp() : void {
        //create some files to search
        $png = mediaFN('wiki:kind_zu_katze.png');
        $ogv = mediaFN('wiki:kind_zu_katze.ogv');
        $webm = mediaFN('wiki:kind_zu_katze.webm');

        $this->save('a.png', $png);
        $this->save('aa.png', $png);
        $this->save('ab.png', $png);

        $this->save('a.ogv', $ogv);
        $this->save('aa.ogv', $ogv);
        $this->save('ab.ogv', $ogv);

        $this->save('a:a.png', $png);
        $this->save('b:a.png', $png);

        $this->save('0.webm', $webm);

    }

    /*
     * Reset media_printfile static variable $twibble to stat state
     */
    protected function reset_media_printfile() {
        $reflect = new ReflectionFunction('media_printfile');
        $static = $reflect->getStaticVariables();
        if ($static['twibble'] == -1) {
            ob_start();
            @media_printfile(array(), 0, '');
            ob_end_clean();
        }
    }

    /**
     * Build search result header as in media_searchlist() with $fullscreen = false
     *
     * @param $query search query
     * @param $ns namespece where we search
     *
     * @return string
     */
    protected function media_searchlist_header($query, $ns) {
        global $lang;

        $header = '<h1 id="media__ns">'.sprintf($lang['searchmedia_in'],hsc($ns).':*').'</h1>'.NL;
        ob_start();
        media_searchform($ns,$query);
        $header .= ob_get_contents();
        ob_end_clean();

        return $header;
    }

    /**
     * Wrap around media_printfile: return the result.
     *
     * @param $item
     * @return string
     */
    protected function media_printfile($item) {
        ob_start();
        media_printfile($item,$item['perm'],'',true);
        $out = ob_get_contents();
        ob_end_clean();

        return $out;
    }

    /**
     * Wrap around media_searchlist: return the result
     * Reset media_printfile static variables afterwards
     *
     * @param $query
     * @param $ns
     * @return string
     */
    protected function media_searchlist($query, $ns) {
        ob_start();
        media_searchlist($query, $ns);
        $out = ob_get_contents();
        ob_end_clean();

        //reset media_printfile static variables
        $this->reset_media_printfile();

        return $out;
    }

    /**
     *
     * @param array[string] $rel_ids media ids relative to $this->upload_ns
     * @return array $items as required by media_printfile
     */
    protected function create_media_items($rel_ids) {
        $items = array();
        foreach ($rel_ids as $rel_id){
            $file = mediaFN($this->upload_ns . ':' . $rel_id);
            $info             = array();
            $info['id']       = $this->upload_ns . ':' . $rel_id;
            $info['perm']     = auth_quickaclcheck(getNS($info['id']).':*');
            $info['file']     = \dokuwiki\Utf8\PhpString::basename($file);
            $info['size']     = filesize($file);
            $info['mtime']    = filemtime($file);
            $info['writable'] = is_writable($file);
            if(preg_match("/\.(jpe?g|gif|png)$/",$file)){
                $info['isimg'] = true;
                $info['meta']  = new JpegMeta($file);
            }else{
                $info['isimg'] = false;
            }
            $info['hash']     = md5(io_readFile(mediaFN($info['id']),false));

            $items[] = $info;
        }
        return $items;
    }

    /**
     * Output result as in 'media_searchlist' but use an arbitrary media IDs list instead of actual searching
     * Reset media_printfile static variables afterwards
     *
     * @param array[string] $rel_ids media ids relative to $this->upload_ns
     * @param string $query actual seqrch query (used for filling search filed input)
     * @param string $ns
     * @return string
     */
    protected function media_searchlist_except($rel_ids, $query, $ns) {
        //build a search result header
        $expect = $this->media_searchlist_header($query, $ns);

        //get the items list
        $items = $this->create_media_items($rel_ids);
        foreach ($items as $item) {
            $expect .= $this->media_printfile($item);
        }

        //reset media_printfile static variables
        $this->reset_media_printfile();

        return $expect;
    }

    public function test_noglobbing(){
        $query = 'a.png';
        $ns = $this->upload_ns;

        $result = $this->media_searchlist($query, $ns);
        $expect = $this->media_searchlist_except(array('a:a.png', 'b:a.png', 'a.png', 'aa.png'), $query, $ns);

        $this->assertEquals($expect, $result);
    }

    public function test_globbing_asterisk(){
        $query = 'a*.png';
        $ns = $this->upload_ns;

        $result = $this->media_searchlist($query, $ns);
        $expect = $this->media_searchlist_except(array('a:a.png', 'b:a.png', 'a.png', 'aa.png', 'ab.png'), $query, $ns);

        $this->assertEquals($expect, $result);
    }

    public function test_globbing_find_by_ext(){
        $query = '*.ogv';
        $ns = $this->upload_ns;

        $result = $this->media_searchlist($query, $ns);
        $expect = $this->media_searchlist_except(array('a.ogv', 'aa.ogv', 'ab.ogv'), $query, $ns);

        $this->assertEquals($expect, $result);
    }

    public function test_globbing_question_mark(){
        $query = 'a?.png';
        $ns = $this->upload_ns;

        $result = $this->media_searchlist($query, $ns);
        $expect = $this->media_searchlist_except(array('aa.png', 'ab.png'), $query, $ns);

        $this->assertEquals($expect, $result);
    }

    public function test_globbing_question_mark_and_asterisk(){
        $query = 'a?.*';
        $ns = $this->upload_ns;

        $result = $this->media_searchlist($query, $ns);
        $expect = $this->media_searchlist_except(array('aa.ogv', 'aa.png', 'ab.ogv', 'ab.png'), $query, $ns);

        $this->assertEquals($expect, $result);
    }

    public function test_globbing_question_mark_on_the_begining(){
        $query = '?.png';
        $ns = $this->upload_ns;

        $result = $this->media_searchlist($query, $ns);
        $expect = $this->media_searchlist_except(array('a:a.png', 'b:a.png', 'a.png'), $query, $ns);

        $this->assertEquals($expect, $result);
    }

    public function test_globbing_two_question_marks_on_the_begining(){
        $query = '??.png';
        $ns = $this->upload_ns;

        $result = $this->media_searchlist($query, $ns);
        $expect = $this->media_searchlist_except(array('aa.png', 'ab.png'), $query, $ns);

        $this->assertEquals($expect, $result);
    }

    public function test_globbing_two_letter_file_names(){
        $query = '??.*';
        $ns = $this->upload_ns;

        $result = $this->media_searchlist($query, $ns);
        $expect = $this->media_searchlist_except(array('aa.ogv', 'aa.png', 'ab.ogv', 'ab.png'), $query, $ns);

        $this->assertEquals($expect, $result);
    }

    public function test_zero_search(){
        $query = '0';
        $ns = $this->upload_ns;

        $result = $this->media_searchlist($query, $ns);
        $expect = $this->media_searchlist_except(array('0.webm'), $query, $ns);

        $this->assertEquals($expect, $result);
    }
}
