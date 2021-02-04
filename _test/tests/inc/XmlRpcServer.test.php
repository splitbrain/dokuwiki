<?php

use dokuwiki\Remote\XmlRpcServer;

class XmlRpcServerTestWrapper extends XmlRpcServer
{
    public $output;

    public function output($xml) {
        $this->output = $xml;
    }
}

class XmlRpcServerTest extends DokuWikiTest
{
    protected $server;

    function setUp() : void
    {
        parent::setUp();
        global $conf;

        $conf['remote'] = 1;
        $conf['remoteuser'] = '';
        $conf['useacl'] = 0;

        $this->server = new XmlRpcServerTestWrapper(true);
    }


    function testDateFormat()
    {
        $pageName = ":wiki:dokuwiki";
        $file = wikiFN($pageName);
        $timestamp = filemtime($file);
        $ixrModifiedTime = (new DateTime('@' . $timestamp))->format(IXR_Date::XMLRPC_ISO8601);

        $request = <<<EOD
<?xml version="1.0"?>
   <methodCall>
     <methodName>wiki.getPageInfo</methodName>
     		<param>
			<value>
				<string>$pageName</string>
			</value>
		</param>
   </methodCall>
EOD;
        $expected = <<<EOD
<methodResponse>
  <params>
    <param>
      <value>
        <struct>
  <member><name>name</name><value><string>wiki:dokuwiki</string></value></member>
  <member><name>lastModified</name><value><dateTime.iso8601>$ixrModifiedTime</dateTime.iso8601></value></member>
  <member><name>author</name><value><string></string></value></member>
  <member><name>version</name><value><int>$timestamp</int></value></member>
</struct>
      </value>
    </param>
  </params>
</methodResponse>
EOD;

        $this->server->serve($request);
        $this->assertEquals(trim($expected), trim($this->server->output));
    }
}
