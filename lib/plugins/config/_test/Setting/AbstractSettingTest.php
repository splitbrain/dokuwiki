<?php

namespace dokuwiki\plugin\config\test\Setting;

use dokuwiki\plugin\config\core\Setting\Setting;

abstract class AbstractSettingTest extends \DokuWikiTest {

    /** @var string the class to test */
    protected $class;

    /**
     * Sets up the proper class to test based on the test's class name
     * @throws \Exception
     */
    public function setUp() : void {
        parent::setUp();
        $class = get_class($this);
        $class = substr($class, strrpos($class, '\\') + 1, -4);
        $class = 'dokuwiki\\plugin\\config\\core\\Setting\\' . $class;
        $this->class = $class;
    }

    public function testInitialBasics() {
        /** @var Setting $setting */
        $setting = new $this->class('test');
        $this->assertEquals('test', $setting->getKey());
        $this->assertSame(false, $setting->isProtected());
        $this->assertSame(true, $setting->isDefault());
        $this->assertSame(false, $setting->hasError());
        $this->assertSame(false, $setting->shouldBeSaved());
    }

    public function testShouldHaveDefault() {
        /** @var Setting $setting */
        $setting = new $this->class('test');
        $this->assertSame(true, $setting->shouldHaveDefault());
    }

    public function testPrettyKey() {
        /** @var Setting $setting */
        $setting = new $this->class('test');
        $this->assertEquals('test', $setting->getPrettyKey(false));

        $setting = new $this->class('test____foo');
        $this->assertEquals('test»foo', $setting->getPrettyKey(false));

        $setting = new $this->class('test');
        $this->assertEquals(
            '<a href="http://www.dokuwiki.org/config:test">test</a>',
            $setting->getPrettyKey(true)
        );

        $setting = new $this->class('test____foo');
        $this->assertEquals('test»foo', $setting->getPrettyKey(true));

        $setting = new $this->class('start');
        $this->assertEquals(
            '<a href="http://www.dokuwiki.org/config:startpage">start</a>',
            $setting->getPrettyKey(true)
        );
    }

    public function testType() {
        /** @var Setting $setting */
        $setting = new $this->class('test');
        $this->assertEquals('dokuwiki', $setting->getType());

        $setting = new $this->class('test_foo');
        $this->assertEquals('dokuwiki', $setting->getType());

        $setting = new $this->class('plugin____test');
        $this->assertEquals('plugin', $setting->getType());

        $setting = new $this->class('tpl____test');
        $this->assertEquals('template', $setting->getType());
    }

    public function testCaution() {
        /** @var Setting $setting */
        $setting = new $this->class('test');
        $this->assertEquals(false, $setting->caution());

        $setting = new $this->class('test', ['_caution' => 'warning']);
        $this->assertEquals('warning', $setting->caution());

        $setting = new $this->class('test', ['_caution' => 'danger']);
        $this->assertEquals('danger', $setting->caution());

        $setting = new $this->class('test', ['_caution' => 'security']);
        $this->assertEquals('security', $setting->caution());

        $setting = new $this->class('test', ['_caution' => 'flargh']);
        $this->expectException(\RuntimeException::class);
        $setting->caution();
    }


}
