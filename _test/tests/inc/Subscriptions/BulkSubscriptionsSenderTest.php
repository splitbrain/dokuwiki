<?php

namespace tests\inc\Subscriptions;

use dokuwiki\Subscriptions\BulkSubscriptionSender;
use dokuwiki\Subscriptions\SubscriberManager;
use dokuwiki\test\mock\MailerMock;
use DokuWikiTest;

class BulkSubscriptionsSenderTest extends DokuWikiTest
{

    private $originalSubscriptionConfig;

    public function setUp() : void
    {
        parent::setUp();
        global $conf;
        $this->originalSubscriptionConfig = $conf['subscribers'];
        $conf['subscribers'] = true;
    }

    protected function tearDown() : void
    {
        global $conf;
        $conf['subscribers'] = $this->originalSubscriptionConfig;
        parent::tearDown();
    }

    public function testBulkdigest()
    {
        $mailerMock = new MailerMock();
        $sub = new BulkSubscriptionSender($mailerMock);
        $manager = new SubscriberManager();

        // let's start with nothing
        $this->assertEquals(0, $sub->sendBulk('sub1:test'));

        // create a subscription
        $manager->add('sub1:', 'testuser', 'digest', '978328800'); // last mod 2001-01-01

        // now create change
        $_SERVER['REMOTE_USER'] = 'someguy';
        saveWikiText('sub1:test', 'foo bar', 'a subscription change', false);

        // should trigger a mail
        $this->assertEquals(1, $sub->sendBulk('sub1:test'));
        $this->assertEquals(['arthur@example.com'], array_column($mailerMock->mails, 'Bcc'));

        $mailerMock->mails = [];

        // now create more changes
        $_SERVER['REMOTE_USER'] = 'someguy';
        saveWikiText('sub1:sub2:test', 'foo bar', 'a subscription change', false);
        saveWikiText('sub1:another_test', 'foo bar', 'a subscription change', false);

        // should not trigger a mail, because the subscription time has not been reached, yet
        $this->assertEquals(0, $sub->sendBulk('sub1:test'));
        $this->assertEquals([], array_column($mailerMock->mails, 'Bcc'));

        // reset the subscription time
        $manager->add('sub1:', 'testuser', 'digest', '978328800'); // last mod 2001-01-01

        // we now should get mails for three changes
        $this->assertEquals(3, $sub->sendBulk('sub1:test'));
        $this->assertEquals(
            ['arthur@example.com', 'arthur@example.com', 'arthur@example.com'],
            array_column($mailerMock->mails, 'Bcc')
        );
    }

    public function testBulklist()
    {
        $mailerMock = new MailerMock();
        $sub = new BulkSubscriptionSender($mailerMock);
        $manager = new SubscriberManager();

        // let's start with nothing
        $this->assertEquals(0, $sub->sendBulk('sub1:test'));

        // create a subscription
        $manager->add('sub1:', 'testuser', 'list', '978328800'); // last mod 2001-01-01

        // now create change
        $_SERVER['REMOTE_USER'] = 'someguy';
        saveWikiText('sub1:test', 'foo bar', 'a subscription change', false);

        // should trigger a mail
        $this->assertEquals(1, $sub->sendBulk('sub1:test'));
        $this->assertEquals(['arthur@example.com'], array_column($mailerMock->mails, 'Bcc'));

        $mailerMock->mails = [];

        // now create more changes
        $_SERVER['REMOTE_USER'] = 'someguy';
        saveWikiText('sub1:sub2:test', 'foo bar', 'a subscription change', false);
        saveWikiText('sub1:another_test', 'foo bar', 'a subscription change', false);

        // should not trigger a mail, because the subscription time has not been reached, yet
        $this->assertEquals(0, $sub->sendBulk('sub1:test'));
        $this->assertEquals([], array_column($mailerMock->mails, 'Bcc'));

        // reset the subscription time
        $manager->add('sub1:', 'testuser', 'list', '978328800'); // last mod 2001-01-01

        // we now should get a single mail for all three changes
        $this->assertEquals(1, $sub->sendBulk('sub1:test'));
        $this->assertEquals(['arthur@example.com'], array_column($mailerMock->mails, 'Bcc'));
    }
}
