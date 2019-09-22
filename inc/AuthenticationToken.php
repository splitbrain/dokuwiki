<?php

namespace dokuwiki;

class AuthenticationToken {
    /** @var int byte length of the token (non-user part) */
    const TOKENLENGTH = 32;

    /** @var string path to the file */
    protected $file;
    /** @var string the md5 of the user name */
    protected $md5;

    /** @var string|null the user name */
    protected $user;
    /** @var string|null the full token */
    protected $token;

    /**
     * AuthenticationToken constructor.
     *
     * @param string $md5
     * @param string|null $user
     */
    protected function __construct($md5, $user = null) {
        global $conf;
        $this->md5 = $md5;
        $this->user = $user;
        $this->file = $conf['metadir'] . '/_authtokens/' . $md5 . '.token';
    }

    /**
     * Create an instance from a given token
     *
     * @param string $token
     * @return AuthenticationToken
     */
    static public function fromToken($token) {
        list($md5) = explode('-', $token);
        if(!preg_match('/^[a-zA-Z0-9]+$/', $md5)) $md5 = 'invalid';
        return new self($md5);
    }

    /**
     * Create an instance from a given user name
     *
     * @param string $user
     * @return AuthenticationToken
     */
    static public function fromUser($user) {
        /** @var \DokuWiki_Auth_Plugin $auth */
        global $auth;
        $md5 = md5($auth->cleanUser($user), true);
        $md5 = self::tokenchars($md5);
        return new self($md5, $user);
    }

    /**
     * Check the user supplied token against the stored one
     *
     * @param string $token the user supplied token
     * @return bool
     */
    public function check($token) {
        $known = $this->getToken();
        if(!$known) return false;
        return hash_equals($known, $token);
    }

    /**
     * Reset the user's token
     *
     * @return string|false the new token if successful
     */
    public function reset() {
        if($this->user === null) throw new \RuntimeException('No user initialized');

        $token = self::tokenchars(random_bytes(self::TOKENLENGTH));
        $token = $this->md5 . '-' . $token;

        $content = $token . "\n" . $this->user;
        if(io_saveFile($this->file, $content)) return $token;
        return false;
    }

    /**
     * Get the user's current token
     *
     * @return string|false the token, false if none is set
     */
    public function getToken() {
        if($this->token === null && !$this->load()) return false;
        return $this->token;
    }

    /**
     * Get the user of this token
     *
     * @return bool|string
     */
    public function getUser() {
        if($this->user === null && !$this->load()) return false;
        return $this->user;
    }

    /**
     * Removes the user's token
     */
    public function clear() {
        @unlink($this->file);
    }

    /**
     * Load the data
     *
     * @return bool successfully loaded
     */
    protected function load() {
        if(!file_exists($this->file)) return false;
        list($token, $user) = explode("\n", trim(io_readFile($this->file, false)));
        $this->user = $user;
        $this->token = $token;
        return true;
    }

    /**
     * Adjusted Base64 method
     *
     * @param string $bytes
     * @return string
     */
    protected static function tokenchars($bytes) {
        $chars = base64_encode($bytes);
        $chars = str_replace(['+', '/', '='], ['X', 'Y', ''], $chars);
        return $chars;
    }

    /**
     * Generate an alphanumeric token of given length
     *
     * @param int $length
     * @return string
     */
    protected function createToken($length = 30) {
        $token = '';
        $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $codeAlphabet .= "abcdefghijklmnopqrstuvwxyz";
        $codeAlphabet .= "0123456789";
        $max = strlen($codeAlphabet);

        for($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[random_int(0, $max - 1)];
        }

        return $token;
    }

}
