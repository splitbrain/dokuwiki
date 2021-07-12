<?php

namespace tests\inc\Utils;

use dokuwiki\Utils\PageResolver;

/**
 * @todo tests that make use of revisions might be wanted
 */
class PageResolverTest extends \DokuWikiTest
{
    /**
     * @return \Generator|array
     * @see testResolveID
     */
    public function provideResolveData()
    {
        $data = [
            // relative current in root
            ['context', 'page', 'page'],
            ['context', '.page', 'page'],
            ['context', '.:page', 'page'],

            // relative current in namespace
            ['lev1:lev2:context', 'page', 'lev1:lev2:page'],
            ['lev1:lev2:context', '.page', 'lev1:lev2:page'],
            ['lev1:lev2:context', '.:page', 'lev1:lev2:page'],

            // relative upper in root
            ['context', '..page', 'page'],
            ['context', '..:page', 'page'],

            // relative upper in namespace
            ['lev1:lev2:context', '..page', 'lev1:page'],
            ['lev1:lev2:context', '..:page', 'lev1:page'],
            ['lev1:lev2:context', '..:..:page', 'page'],
            ['lev1:lev2:context', '..:..:..:page', 'page'],

            // deeper nesting
            ['lev1:lev2:lev3:context', '..page', 'lev1:lev2:page'],
            ['lev1:lev2:lev3:context', '..:page', 'lev1:lev2:page'],
            ['lev1:lev2:lev3:context', '..:..page', 'lev1:page'],
            ['lev1:lev2:lev3:context', '..:..:page', 'lev1:page'],
            ['lev1:lev2:lev3:context', '..:..:..page', 'page'],
            ['lev1:lev2:lev3:context', '..:..:..:page', 'page'],
            ['lev1:lev2:lev3:context', '..:..:..:..page', 'page'],
            ['lev1:lev2:lev3:context', '..:..:..:..:page', 'page'],

            // strange and broken ones
            ['lev1:lev2:context', '....:....:page', 'lev1:lev2:page'],
            ['lev1:lev2:context', '..:..:lev3:page', 'lev3:page'],
            ['lev1:lev2:context', '..:..:lev3:..:page', 'page'],
            ['lev1:lev2:context', '..:..:lev3:..:page:....:...', 'page'],

            // relative to current page
            ['context', '~page', 'context:page'],
            ['context', '~:page', 'context:page'],
            ['lev1:lev2:context', '~page', 'lev1:lev2:context:page'],
            ['lev1:lev2:context', '~:page', 'lev1:lev2:context:page'],

            // start pages
            ['context', '.:', 'start'],
            ['foo:context', '.:', 'foo:start'],
            ['context', 'foo:', 'foo:start'],
            ['foo:context', 'foo:', 'foo:start'],
            ['context', '~foo:', 'context:foo:start'],
            ['foo:context', '~foo:', 'foo:context:foo:start'],

            // empty page links to itself
            ['context', '', 'context'],
        ];

        // run each test without a hash
        foreach ($data as $row) {
            yield $row;
        }

        // run each test with a hash
        foreach ($data as $row) {
            $row[1] .= '#somehash';
            $row[2] .= '#somehash';
            yield $row;
        }
    }

    /**
     * @dataProvider provideResolveData
     * @param string $context
     * @param string $id
     * @param string $expected
     */
    public function testResolveID($context, $id, $expected)
    {
        $resolver = new PageResolver($context);
        $this->assertEquals($expected, $resolver->resolveId($id));
    }

}
