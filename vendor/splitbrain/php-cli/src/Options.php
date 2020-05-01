<?php

namespace splitbrain\phpcli;

/**
 * Class Options
 *
 * Parses command line options passed to the CLI script. Allows CLI scripts to easily register all accepted options and
 * commands and even generates a help text from this setup.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @license MIT
 */
class Options
{
    /** @var  array keeps the list of options to parse */
    protected $setup;

    /** @var  array store parsed options */
    protected $options = array();

    /** @var string current parsed command if any */
    protected $command = '';

    /** @var  array passed non-option arguments */
    protected $args = array();

    /** @var  string the executed script */
    protected $bin;

    /** @var  Colors for colored help output */
    protected $colors;

    /**
     * Constructor
     *
     * @param Colors $colors optional configured color object
     * @throws Exception when arguments can't be read
     */
    public function __construct(Colors $colors = null)
    {
        if (!is_null($colors)) {
            $this->colors = $colors;
        } else {
            $this->colors = new Colors();
        }

        $this->setup = array(
            '' => array(
                'opts' => array(),
                'args' => array(),
                'help' => ''
            )
        ); // default command

        $this->args = $this->readPHPArgv();
        $this->bin = basename(array_shift($this->args));

        $this->options = array();
    }
    
    /**
     * Gets the bin value
     */
    public function getBin()
    {
        return $this->bin;
    }

    /**
     * Sets the help text for the tool itself
     *
     * @param string $help
     */
    public function setHelp($help)
    {
        $this->setup['']['help'] = $help;
    }

    /**
     * Register the names of arguments for help generation and number checking
     *
     * This has to be called in the order arguments are expected
     *
     * @param string $arg argument name (just for help)
     * @param string $help help text
     * @param bool $required is this a required argument
     * @param string $command if theses apply to a sub command only
     * @throws Exception
     */
    public function registerArgument($arg, $help, $required = true, $command = '')
    {
        if (!isset($this->setup[$command])) {
            throw new Exception("Command $command not registered");
        }

        $this->setup[$command]['args'][] = array(
            'name' => $arg,
            'help' => $help,
            'required' => $required
        );
    }

    /**
     * This registers a sub command
     *
     * Sub commands have their own options and use their own function (not main()).
     *
     * @param string $command
     * @param string $help
     * @throws Exception
     */
    public function registerCommand($command, $help)
    {
        if (isset($this->setup[$command])) {
            throw new Exception("Command $command already registered");
        }

        $this->setup[$command] = array(
            'opts' => array(),
            'args' => array(),
            'help' => $help
        );

    }

    /**
     * Register an option for option parsing and help generation
     *
     * @param string $long multi character option (specified with --)
     * @param string $help help text for this option
     * @param string|null $short one character option (specified with -)
     * @param bool|string $needsarg does this option require an argument? give it a name here
     * @param string $command what command does this option apply to
     * @throws Exception
     */
    public function registerOption($long, $help, $short = null, $needsarg = false, $command = '')
    {
        if (!isset($this->setup[$command])) {
            throw new Exception("Command $command not registered");
        }

        $this->setup[$command]['opts'][$long] = array(
            'needsarg' => $needsarg,
            'help' => $help,
            'short' => $short
        );

        if ($short) {
            if (strlen($short) > 1) {
                throw new Exception("Short options should be exactly one ASCII character");
            }

            $this->setup[$command]['short'][$short] = $long;
        }
    }

    /**
     * Checks the actual number of arguments against the required number
     *
     * Throws an exception if arguments are missing.
     *
     * This is run from CLI automatically and usually does not need to be called directly
     *
     * @throws Exception
     */
    public function checkArguments()
    {
        $argc = count($this->args);

        $req = 0;
        foreach ($this->setup[$this->command]['args'] as $arg) {
            if (!$arg['required']) {
                break;
            } // last required arguments seen
            $req++;
        }

        if ($req > $argc) {
            throw new Exception("Not enough arguments", Exception::E_OPT_ARG_REQUIRED);
        }
    }

    /**
     * Parses the given arguments for known options and command
     *
     * The given $args array should NOT contain the executed file as first item anymore! The $args
     * array is stripped from any options and possible command. All found otions can be accessed via the
     * getOpt() function
     *
     * Note that command options will overwrite any global options with the same name
     *
     * This is run from CLI automatically and usually does not need to be called directly
     *
     * @throws Exception
     */
    public function parseOptions()
    {
        $non_opts = array();

        $argc = count($this->args);
        for ($i = 0; $i < $argc; $i++) {
            $arg = $this->args[$i];

            // The special element '--' means explicit end of options. Treat the rest of the arguments as non-options
            // and end the loop.
            if ($arg == '--') {
                $non_opts = array_merge($non_opts, array_slice($this->args, $i + 1));
                break;
            }

            // '-' is stdin - a normal argument
            if ($arg == '-') {
                $non_opts = array_merge($non_opts, array_slice($this->args, $i));
                break;
            }

            // first non-option
            if ($arg{0} != '-') {
                $non_opts = array_merge($non_opts, array_slice($this->args, $i));
                break;
            }

            // long option
            if (strlen($arg) > 1 && $arg{1} == '-') {
                $arg = explode('=', substr($arg, 2), 2);
                $opt = array_shift($arg);
                $val = array_shift($arg);

                if (!isset($this->setup[$this->command]['opts'][$opt])) {
                    throw new Exception("No such option '$opt'", Exception::E_UNKNOWN_OPT);
                }

                // argument required?
                if ($this->setup[$this->command]['opts'][$opt]['needsarg']) {
                    if (is_null($val) && $i + 1 < $argc && !preg_match('/^--?[\w]/', $this->args[$i + 1])) {
                        $val = $this->args[++$i];
                    }
                    if (is_null($val)) {
                        throw new Exception("Option $opt requires an argument",
                            Exception::E_OPT_ARG_REQUIRED);
                    }
                    $this->options[$opt] = $val;
                } else {
                    $this->options[$opt] = true;
                }

                continue;
            }

            // short option
            $opt = substr($arg, 1);
            if (!isset($this->setup[$this->command]['short'][$opt])) {
                throw new Exception("No such option $arg", Exception::E_UNKNOWN_OPT);
            } else {
                $opt = $this->setup[$this->command]['short'][$opt]; // store it under long name
            }

            // argument required?
            if ($this->setup[$this->command]['opts'][$opt]['needsarg']) {
                $val = null;
                if ($i + 1 < $argc && !preg_match('/^--?[\w]/', $this->args[$i + 1])) {
                    $val = $this->args[++$i];
                }
                if (is_null($val)) {
                    throw new Exception("Option $arg requires an argument",
                        Exception::E_OPT_ARG_REQUIRED);
                }
                $this->options[$opt] = $val;
            } else {
                $this->options[$opt] = true;
            }
        }

        // parsing is now done, update args array
        $this->args = $non_opts;

        // if not done yet, check if first argument is a command and reexecute argument parsing if it is
        if (!$this->command && $this->args && isset($this->setup[$this->args[0]])) {
            // it is a command!
            $this->command = array_shift($this->args);
            $this->parseOptions(); // second pass
        }
    }

    /**
     * Get the value of the given option
     *
     * Please note that all options are accessed by their long option names regardless of how they were
     * specified on commandline.
     *
     * Can only be used after parseOptions() has been run
     *
     * @param mixed $option
     * @param bool|string $default what to return if the option was not set
     * @return bool|string|string[]
     */
    public function getOpt($option = null, $default = false)
    {
        if ($option === null) {
            return $this->options;
        }

        if (isset($this->options[$option])) {
            return $this->options[$option];
        }
        return $default;
    }

    /**
     * Return the found command if any
     *
     * @return string
     */
    public function getCmd()
    {
        return $this->command;
    }

    /**
     * Get all the arguments passed to the script
     *
     * This will not contain any recognized options or the script name itself
     *
     * @return array
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * Builds a help screen from the available options. You may want to call it from -h or on error
     *
     * @return string
     *
     * @throws Exception
     */
    public function help()
    {
        $tf = new TableFormatter($this->colors);
        $text = '';

        $hascommands = (count($this->setup) > 1);
        foreach ($this->setup as $command => $config) {
            $hasopts = (bool)$this->setup[$command]['opts'];
            $hasargs = (bool)$this->setup[$command]['args'];

            // usage or command syntax line
            if (!$command) {
                $text .= $this->colors->wrap('USAGE:', Colors::C_BROWN);
                $text .= "\n";
                $text .= '   ' . $this->bin;
                $mv = 2;
            } else {
                $text .= "\n";
                $text .= $this->colors->wrap('   ' . $command, Colors::C_PURPLE);
                $mv = 4;
            }

            if ($hasopts) {
                $text .= ' ' . $this->colors->wrap('<OPTIONS>', Colors::C_GREEN);
            }

            if (!$command && $hascommands) {
                $text .= ' ' . $this->colors->wrap('<COMMAND> ...', Colors::C_PURPLE);
            }

            foreach ($this->setup[$command]['args'] as $arg) {
                $out = $this->colors->wrap('<' . $arg['name'] . '>', Colors::C_CYAN);

                if (!$arg['required']) {
                    $out = '[' . $out . ']';
                }
                $text .= ' ' . $out;
            }
            $text .= "\n";

            // usage or command intro
            if ($this->setup[$command]['help']) {
                $text .= "\n";
                $text .= $tf->format(
                    array($mv, '*'),
                    array('', $this->setup[$command]['help'] . "\n")
                );
            }

            // option description
            if ($hasopts) {
                if (!$command) {
                    $text .= "\n";
                    $text .= $this->colors->wrap('OPTIONS:', Colors::C_BROWN);
                }
                $text .= "\n";
                foreach ($this->setup[$command]['opts'] as $long => $opt) {

                    $name = '';
                    if ($opt['short']) {
                        $name .= '-' . $opt['short'];
                        if ($opt['needsarg']) {
                            $name .= ' <' . $opt['needsarg'] . '>';
                        }
                        $name .= ', ';
                    }
                    $name .= "--$long";
                    if ($opt['needsarg']) {
                        $name .= ' <' . $opt['needsarg'] . '>';
                    }

                    $text .= $tf->format(
                        array($mv, '30%', '*'),
                        array('', $name, $opt['help']),
                        array('', 'green', '')
                    );
                    $text .= "\n";
                }
            }

            // argument description
            if ($hasargs) {
                if (!$command) {
                    $text .= "\n";
                    $text .= $this->colors->wrap('ARGUMENTS:', Colors::C_BROWN);
                }
                $text .= "\n";
                foreach ($this->setup[$command]['args'] as $arg) {
                    $name = '<' . $arg['name'] . '>';

                    $text .= $tf->format(
                        array($mv, '30%', '*'),
                        array('', $name, $arg['help']),
                        array('', 'cyan', '')
                    );
                }
            }

            // head line and intro for following command documentation
            if (!$command && $hascommands) {
                $text .= "\n";
                $text .= $this->colors->wrap('COMMANDS:', Colors::C_BROWN);
                $text .= "\n";
                $text .= $tf->format(
                    array($mv, '*'),
                    array('', 'This tool accepts a command as first parameter as outlined below:')
                );
                $text .= "\n";
            }
        }

        return $text;
    }

    /**
     * Safely read the $argv PHP array across different PHP configurations.
     * Will take care on register_globals and register_argc_argv ini directives
     *
     * @throws Exception
     * @return array the $argv PHP array or PEAR error if not registered
     */
    private function readPHPArgv()
    {
        global $argv;
        if (!is_array($argv)) {
            if (!@is_array($_SERVER['argv'])) {
                if (!@is_array($GLOBALS['HTTP_SERVER_VARS']['argv'])) {
                    throw new Exception(
                        "Could not read cmd args (register_argc_argv=Off?)",
                        Exception::E_ARG_READ
                    );
                }
                return $GLOBALS['HTTP_SERVER_VARS']['argv'];
            }
            return $_SERVER['argv'];
        }
        return $argv;
    }
}

