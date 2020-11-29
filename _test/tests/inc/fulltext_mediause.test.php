<?php

use dokuwiki\Search\Indexer;
use dokuwiki\Search\MetadataIndex;

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

/**
 * Test cases for the media usage index
 *
 * @author Michael Hamann <michael@content-space.de>
 */
class fultext_mediause_test extends DokuWikiTest
{
    public function test_internalmedia()
    {
        saveWikiText('test:internalmedia_usage', '{{internalmedia.png}} {{..:internal media.png}}', 'Test initialization');
        (new Indexer('test:internalmedia_usage'))->addPage();
        $MetadataIndex = new MetadataIndex();

        $this->assertEquals(array('test:internalmedia_usage'), $MetadataIndex->mediause('internal_media.png'));
        $this->assertEquals(array('test:internalmedia_usage'), $MetadataIndex->mediause('test:internalmedia.png'));
    }

    public function test_media_in_links()
    {
        saveWikiText('test:medialinks', '[[doku>wiki:dokuwiki|{{wiki:logo.png}}]] [[http://www.example.com|{{example.png?200x800}}]]', 'Test init');
        (new Indexer('test:medialinks'))->addPage();
        $MetadataIndex = new MetadataIndex();

        $this->assertEquals(array('test:medialinks'), $MetadataIndex->mediause('wiki:logo.png'));
        $this->assertEquals(array('test:medialinks'), $MetadataIndex->mediause('test:example.png'));
    }

    public function test_media_in_local_links()
    {
        saveWikiText('test:locallinks', '[[#test|{{wiki:logolocal.png}}]]', 'Test init');
        (new Indexer('test:locallinks'))->addPage();
        $MetadataIndex = new MetadataIndex();

        $this->assertEquals(array('test:locallinks'), $MetadataIndex->mediause('wiki:logolocal.png'));
    }

    public function test_media_in_footnotes()
    {
        saveWikiText('test:media_footnotes', '(({{footnote.png?20x50}} [[foonote|{{:footlink.png}}]]))', 'Test initialization');
        (new Indexer('test:media_footnotes'))->addPage();
        $MetadataIndex = new MetadataIndex();

        $this->assertEquals(array('test:media_footnotes'), $MetadataIndex->mediause('test:footnote.png'));
        $this->assertEquals(array('test:media_footnotes'), $MetadataIndex->mediause('footlink.png'));
    }

    public function test_media_in_hidden_pages()
    {
        global $conf;
        $conf['hidepages'] = 'hidden:.*';
        saveWikiText('hidden:medias', '[[doku>wiki:dokuwiki|{{wiki:hiddenlogo.png}}]]', 'Test initialization');
        (new Indexer('hidden:medias'))->addPage();
        $MetadataIndex = new MetadataIndex();

        $this->assertEquals(array(), $MetadataIndex->mediause('wiki:hiddenlogo.png'));
        $this->assertEquals(array(), $MetadataIndex->mediause('wiki:hiddenlogo.png', false));
        $this->assertEquals(array('hidden:medias'), $MetadataIndex->mediause('wiki:hiddenlogo.png', true));
    }

    public function test_media_in_protected_pages()
    {
        global $conf;
        global $AUTH_ACL;
        $conf['superuser'] = 'alice';
        $conf['useacl']    = 1;

        $AUTH_ACL = array(
            '*           @ALL           8',
            'secret:*      @ALL           0',
        );

        $_SERVER['REMOTE_USER'] = 'eve';

        saveWikiText('secret:medias', '[[doku>wiki:dokuwiki|{{wiki:secretlogo.png}}]]', 'Test initialization');
        (new Indexer('secret:medias'))->addPage();
        $MetadataIndex = new MetadataIndex();

        $this->assertEquals(array(), $MetadataIndex->mediause('wiki:secretlogo.png'));
        $this->assertEquals(array(), $MetadataIndex->mediause('wiki:secretlogo.png', false));
        $this->assertEquals(array('secret:medias'), $MetadataIndex->mediause('wiki:secretlogo.png', true));
    }

    public function test_media_in_deleted_pages()
    {
        saveWikiText('test:internalmedia_usage', '{{internalmedia.png}} {{..:internal media.png}}', 'Test initialization');
        (new Indexer('test:internalmedia_usage'))->addPage();
        saveWikiText('test:internalmedia_usage', '', 'Deleted');
        $MetadataIndex = new MetadataIndex();

        $this->assertEquals(array(), $MetadataIndex->mediause('internal_media.png'));
        $this->assertEquals(array(), $MetadataIndex->mediause('test:internalmedia.png'));
    }
}
