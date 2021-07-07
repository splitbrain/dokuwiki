<?php

class common_clientIP_test extends DokuWikiTest {

    function setup() : void {
        parent::setup();

        global $conf;
        $conf['trustedproxy'] = '^(::1|[fF][eE]80:|127\.|10\.|192\.168\.|172\.((1[6-9])|(2[0-9])|(3[0-1]))\.)';
    }

    function test_simple_all(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
        $out = '123.123.123.123';
        $this->assertEquals($out, clientIP());
    }

    function test_proxy1_all(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '77.77.77.77';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
        $out = '123.123.123.123,77.77.77.77';
        $this->assertEquals($out, clientIP());
    }

    function test_proxy2_all(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '77.77.77.77';
        $out = '123.123.123.123,77.77.77.77';
        $this->assertEquals($out, clientIP());
    }

    function test_proxyhops_all(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '77.77.77.77,66.66.66.66';
        $out = '123.123.123.123,77.77.77.77,66.66.66.66';
        $this->assertEquals($out, clientIP());
    }

    function test_simple_single(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
        $out = '123.123.123.123';
        $this->assertEquals($out, clientIP(true));
    }

    function test_proxy1_single(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '77.77.77.77';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
        $out = '123.123.123.123';
        $this->assertEquals($out, clientIP(true));
    }

    function test_proxy2_single(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '77.77.77.77';
        $out = '123.123.123.123';
        $this->assertEquals($out, clientIP(true));
    }

    function test_proxyhops_single(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '77.77.77.77,66.66.66.66';
        $out = '123.123.123.123';
        $this->assertEquals($out, clientIP(true));
    }

    function test_proxy1_local_single(){
        $_SERVER['REMOTE_ADDR']          = '127.0.0.1';
        $_SERVER['HTTP_X_REAL_IP']       = '77.77.77.77';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
        $out = '77.77.77.77';
        $this->assertEquals($out, clientIP(true));
    }

    function test_proxy2_local_single(){
        $_SERVER['REMOTE_ADDR']          = '127.0.0.1';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '77.77.77.77';
        $out = '77.77.77.77';
        $this->assertEquals($out, clientIP(true));
    }

    function test_proxyhops1_local_single(){
        $_SERVER['REMOTE_ADDR']          = '127.0.0.1';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '77.77.77.77,66.66.66.66';
        $out = '77.77.77.77';
        $this->assertEquals($out, clientIP(true));
    }

    function test_proxyhops2_local_single(){
        $_SERVER['REMOTE_ADDR']          = '127.0.0.1';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1,66.66.66.66';
        $out = '66.66.66.66';
        $this->assertEquals($out, clientIP(true));
    }

    function test_local_all(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';
        $out = '123.123.123.123,127.0.0.1';
        $this->assertEquals($out, clientIP());
    }

    function test_local1_single(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';
        $out = '123.123.123.123';
        $this->assertEquals($out, clientIP(true));
    }

    function test_local2_single(){
        $_SERVER['REMOTE_ADDR']          = '127.0.0.1';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '123.123.123.123';
        $out = '123.123.123.123';
        $this->assertEquals($out, clientIP(true));
    }

    function test_local3_single(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1,10.0.0.1,192.168.0.2,172.17.1.1,172.21.1.1,172.31.1.1';
        $out = '123.123.123.123';
        $this->assertEquals($out, clientIP(true));
    }

    function test_local4_single(){
        $_SERVER['REMOTE_ADDR']          = '127.0.0.1';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.0.5';
        $out = '192.168.0.5';
        $this->assertEquals($out, clientIP(true));
    }

    function test_garbage_all(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'some garbage, or something, 222';
        $out = '123.123.123.123';
        $this->assertEquals($out, clientIP());
    }

    function test_garbage_single(){
        $_SERVER['REMOTE_ADDR']          = '123.123.123.123';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'some garbage, or something, 222';
        $out = '123.123.123.123';
        $this->assertEquals($out, clientIP(true));
    }

    function test_garbageonly_all(){
        $_SERVER['REMOTE_ADDR']          = 'argh';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'some garbage, or something, 222';
        $out = '0.0.0.0';
        $this->assertEquals($out, clientIP());
    }

    function test_garbageonly_single(){
        $_SERVER['REMOTE_ADDR']          = 'argh';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'some garbage, or something, 222';
        $out = '0.0.0.0';
        $this->assertEquals($out, clientIP(true));
    }

    function test_malicious(){
        $_SERVER['REMOTE_ADDR']          = '';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '<?php set_time_limit(0);echo \'my_delim\';passthru(123.123.123.123);die;?>';
        $out = '0.0.0.0';
        $this->assertEquals($out, clientIP());
    }

    function test_malicious_with_remote_addr(){
        $_SERVER['REMOTE_ADDR']          = '8.8.8.8';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '<?php set_time_limit(0);echo \'my_delim\';passthru(\',123.123.123.123,\');die;?>';
        $out = '8.8.8.8';
        $this->assertEquals($out, clientIP(true));
    }

    function test_proxied_malicious_with_remote_addr(){
        $_SERVER['REMOTE_ADDR']          = '127.0.0.1';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '8.8.8.8,<?php set_time_limit(0);echo \'my_delim\';passthru(\',123.123.123.123,\');die;?>';
        $out = '127.0.0.1,8.8.8.8,123.123.123.123';
        $this->assertEquals($out, clientIP());
    }


    // IPv6

    function test_simple_single_ipv6(){
        $_SERVER['REMOTE_ADDR']          = '1234:1234:1234:1234:1234:1234:1234:1234';
        $_SERVER['HTTP_X_REAL_IP']       = '';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
        $out = '1234:1234:1234:1234:1234:1234:1234:1234';
        $this->assertEquals($out, clientIP(true));
    }

    function test_proxyhops_garbage_all_ipv4_and_ipv6(){
        $_SERVER['REMOTE_ADDR']          = '1234:1234:1234:1234:1234:1234:1234:1234';
        $_SERVER['HTTP_X_REAL_IP']       = '1.1.1.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '777:777:777:777:777:777:777:777,::1,skipme,66.66.66.66';
        $out = '1234:1234:1234:1234:1234:1234:1234:1234,777:777:777:777:777:777:777:777,::1,66.66.66.66,1.1.1.1';
        $this->assertEquals($out, clientIP());
    }

}

//Setup VIM: ex: et ts=4 :
