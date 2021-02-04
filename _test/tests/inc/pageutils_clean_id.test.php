<?php

class init_clean_id_test extends DokuWikiTest
{

    /** @inheritDoc */
    function teardown() : void
    {
        global $cache_cleanid;
        $cache_cleanid = array();
    }

    /**
     * DataProvider
     *
     * @return Generator|array
     * @see testCleanId
     */
    public function provideTestData()
    {
        // input, expected, optional options array
        $tests = [
            ['page', 'page'],
            ['pa_ge', 'pa_ge'],
            ['pa%ge', 'pa_ge'],
            ['pa#ge', 'pa_ge'],
            ['pàge', 'page'],
            ['pagĖ', 'page'],
            ['pa$%^*#ge', 'pa_ge'],
            ['*page*', 'page'],
            ['ښ', 'ښ'],
            ['päge', 'paege'],
            ['foo bar', 'foo_bar'],
            ['PÄGÖ', 'paegoe'],
            ['Faß', 'fass'],
            ['ښ侧化并곦  β', 'ښ侧化并곦_β'],
            ['page:page', 'page:page'],
            ['page;page', 'page:page'],
            ['page:page 1.2', 'page:page_1.2'],
            ['page._#!', 'page'],
            ['._#!page', 'page'],
            ['page._#!page', 'page._page'],
            ['ns._#!:page', 'ns:page'],
            ['ns:._#!page', 'ns:page'],
            ['ns._#!ns:page', 'ns._ns:page'],
            ['ns_:page', 'ns:page'],
            ['page...page', 'page...page'],
            ['page---page', 'page---page'],
            ['page___page', 'page_page'],
            ['page_-.page', 'page_-.page'],
            [':page', 'page'],
            [':ns:page', 'ns:page'],
            ['page:', 'page'],
            ['ns:page:', 'ns:page'],

            // use-slash handling
            ['page/page', 'page_page', ['useslash' => 0]],
            ['page/page', 'page:page', ['useslash' => 1]],

            // different sep-char
            ['pa-ge', 'pa-ge', ['sepchar' => '-']],
            ['pa%ge', 'pa-ge', ['sepchar' => '-']],

            // no deaccenting
            ['pàge', 'pàge', ['deaccent' => 0]],
            ['pagĖ', 'pagė', ['deaccent' => 0]],
            ['pagĒēĔĕĖėĘęĚě', 'pagēēĕĕėėęęěě', ['deaccent' => 0]],
            ['ښ', 'ښ', ['deaccent' => 0]],
            ['ښ侧化并곦ঝഈ', 'ښ侧化并곦ঝഈ', ['deaccent' => 0]],

            // romanize
            ['pàge', 'page', ['deaccent' => 2]],
            ['pagĖ', 'page', ['deaccent' => 2]],
            ['pagĒēĔĕĖėĘęĚě', 'pageeeeeeeeee', ['deaccent' => 2]],
            ['ښ', 'ښ', ['deaccent' => 2]],
            ['ښ侧化并곦ঝഈ', 'ښ侧化并곦ঝഈ', ['deaccent' => 2]],

            // deaccent and force ascii
            ['pàge', 'page', ['deaccent' => 1, 'ascii' => true]],
            ['pagĖ', 'page', ['deaccent' => 1, 'ascii' => true]],
            ['pagĒēĔĕĖėĘęĚě', 'pageeeeeeeeee', ['deaccent' => 1, 'ascii' => true]],
            ['ښ', '', ['deaccent' => 1, 'ascii' => true]],
            ['ښ侧化并곦ঝഈ', '', ['deaccent' => 1, 'ascii' => true]],

            // romanize and force ascii
            ['pàge', 'page', ['deaccent' => 2, 'ascii' => true]],
            ['pagĖ', 'page', ['deaccent' => 2, 'ascii' => true]],
            ['pagĒēĔĕĖėĘęĚě', 'pageeeeeeeeee', ['deaccent' => 2, 'ascii' => true]],
            ['ښ', '', ['deaccent' => 2, 'ascii' => true]],
            ['ښ侧化并곦ঝഈ', '', ['deaccent' => 2, 'ascii' => true]],
        ];

        foreach ($tests as $test) {
            // defaults
            $sepchar = $test[2]['sepchar'] ?: '_';
            $deaccent = isset($test[2]['deaccent']) ? $test[2]['deaccent'] : 1;
            $ascii = $test[2]['ascii'] ?: false;

            // unless set, test both useslash settings
            if (isset($test[2]['useslash'])) {
                yield([$test[0], $test[1], $ascii, $sepchar, $deaccent, $test[2]['useslash']]);
            } else {
                yield([$test[0], $test[1], $ascii, $sepchar, $deaccent, 0]);
                yield([$test[0], $test[1], $ascii, $sepchar, $deaccent, 1]);
            }
        }
    }

    /**
     * @dataProvider provideTestData
     * @param string $input
     * @param string $expected
     * @param bool $ascii
     * @param string $sepchar
     * @param int $deaccent
     * @param int $useslash
     */
    function testCleanId($input, $expected, $ascii, $sepchar, $deaccent, $useslash)
    {
        // set dokuwiki defaults
        global $conf;
        $conf['sepchar'] = $sepchar;
        $conf['deaccent'] = $deaccent;
        $conf['useslash'] = $useslash;

        $result = cleanID($input, $ascii);
        $this->assertEquals($expected, $result);
    }



    function test_caching_ascii()
    {
        global $conf;
        $conf['deaccent'] = 0;
        $this->assertEquals('pàge', cleanID('pàge', false));
        $this->assertEquals('page', cleanID('pàge', true));

        $this->assertEquals('page', cleanID('pagĖ', true));
        $this->assertEquals('pagė', cleanID('pagĖ', false));
    }

}
