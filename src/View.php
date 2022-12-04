<?php
declare(strict_types=1);

namespace Sura\View;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use DateTime;
use Exception;
use InvalidArgumentException;
use ParseError;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_pop;
use function array_reverse;
use function array_shift;
use function array_splice;
use function array_unshift;
use function bin2hex;
use function call_user_func;
use function count;
use function defined;
use function end;
use function error_get_last;
use function explode;
use function extract;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function filesize;
use function fopen;
use function func_get_args;
use function function_exists;
use function fwrite;
use function getcwd;
use function htmlentities;
use function implode;
use function in_array;
use function is_array;
use function is_file;
use function is_null;
use function is_numeric;
use function is_object;
use function json_encode;
use function ltrim;
use function mb_strpos;
use function md5;
use function method_exists;
use function mkdir;
use function ob_end_clean;
use function ob_get_clean;
use function ob_get_level;
use function ob_start;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function print_r;
use function random_bytes;
use function reset;
use function rtrim;
use function sha1;
use function str_contains;
use function str_repeat;
use function str_replace;
use function stripcslashes;
use function strlen;
use function strpos;
use function substr;
use function substr_count;
use function token_get_all;
use function trim;
use function ucfirst;
use function uksort;
use function var_dump;

/**
 * View - A View Template implementation
 * @since 1.0
 * @version   1.0
 */
class View
{
    use Compilers\Concerns\CompilesAuthorizations,
        Compilers\Concerns\CompilesComments,
        Compilers\Concerns\CompilesComponents,
        Compilers\Concerns\CompilesConditionals,
        Compilers\Concerns\CompilesEchos,
        Compilers\Concerns\CompilesErrors,
        Compilers\Concerns\CompilesHelpers,
        Compilers\Concerns\CompilesIncludes,
        Compilers\Concerns\CompilesInjections,
        Compilers\Concerns\CompilesJson,
        Compilers\Concerns\CompilesLayouts,
        Compilers\Concerns\CompilesLoops,
        Compilers\Concerns\CompilesRawPhp,
        Compilers\Concerns\CompilesStacks;

    //<editor-fold desc="fields">
    public const VERSION = '4.7.1';
    /** @var int View reads if the compiled file has changed. If it has changed,then the file is replaced. */
    public const MODE_AUTO = 0;
    /** @var int Then compiled file is always replaced. It's slow and it's useful for development. */
    public const MODE_SLOW = 1;
    /** @var int The compiled file is never replaced. It's fast and it's useful for production. */
    public const MODE_FAST = 2;
    /** @var int DEBUG MODE, the file is always compiled and the filename is identifiable. */
    public const MODE_DEBUG = 5;
    /** @var array Hold dictionary of translations */
    public static array $dictionary = [];
    /** @var string PHP tag. You could use < ?php or < ? (if shorttag is active in php.ini) */
    public string $phpTag = '<?php '; // hello hello hello.
    /** @var string this line is used to easily echo a value */
    protected string $phpTagEcho = '<?php' . ' echo ';
    /** @var string $currentUser Current user. Example: john */
    public string $currentUser;
    /** @var string $currentRole Current role. Example: admin */
    public $currentRole;
    /** @var string[] $currentPermission Current permission. Example ['edit', 'add'] */
    public array $currentPermission = [];
    /** @var callable callback of validation. It is used for @can,@cannot */
    public $authCallBack;
    /** @var callable callback of validation. It is used for @canany */
    public $authAnyCallBack;
    /** @var callable callback of errors. It is used for @error */
    public $errorCallBack;
    /** @var bool if true then, if the operation fails, and it is critic, then it throws an error */
    public bool $throwOnError = false;
    /** @var string security token */
    public string $csrf_token = '';
    /** @var string The path to the missing translations log file. If empty then every missing key is not saved. */
    public string $missingLog = '';
    /** @var bool */
    public bool $pipeEnable = false;
    /** @var array Alias (with or without namespace) of the classes */
    public array $aliasClasses = [];
    /**
     * @var bool if true then the variables defined in the "include" as arguments are scoped to work only
     * inside the include.<br>
     * If false (default value), then the variables defined in the include as arguments are defined globally.<br>
     * <b>Example: (includeScope=false)</b><br>
     * <pre>
     * @include("template",['a1'=>'abc']) // a1 is equals to abc
     * @include("template",[]) // a1 is equals to abc
     * </pre>
     * <b>Example: (includeScope=true)</b><br>
     * <pre>
     * @include("template",['a1'=>'abc']) // a1 is equals to abc
     * @include("template",[]) // a1 is not defined
     * </pre>
     */
    public bool $includeScope = false;
    /**
     * @var callable[] It allows to parse the compiled output using a function.
     * This function doesn't require to return a value<br>
     * <b>Example:</b> this converts all compiled result in uppercase (note, content is a ref)
     * <pre>
     * $this->compileCallbacks[]= static function (&$content, $templatename=null) {
     *      $content=strtoupper($content);
     * };
     * </pre>
     */
    public $compileCallbacks = [];
    /** @var array All the registered extensions. */
    protected array $extensions = [];
    /** @var array All the finished, captured sections. */
    protected array $sections = [];
    /** @var string The template currently being compiled. For example "folder.template" */
    protected string $fileName;
    protected string $currentView;
    protected string $notFoundPath;
    /** @var string File extension for the template files. */
    protected string $fileExtension = '.blade.php';
    /** @var array The stack of in-progress sections. */
    protected array $sectionStack = [];
    /** @var array The stack of in-progress loops. */
    protected array $loopsStack = [];
    /** @var array Dictionary of variables */
    protected array $variables = [];
    /** @var array Dictionary of global variables */
    protected array $variablesGlobal = [];
    /** @var array All the available compiler functions. */
    protected array $compilers = [
        'Extensions',
        'Statements',
        'Comments',
        'Echos',
    ];
    /** @var string|null it allows to set the stack */
    protected ?string $viewStack;
    /** @var array used by $this->composer() */
    protected array $composerStack = [];
    /** @var array The stack of in-progress push sections. */
    protected array $pushStack = [];
    /** @var array All the finished, captured push sections. */
    protected array $pushes = [];
    /** @var int The number of active rendering operations. */
    protected int $renderCount = 0;
    /** @var string[] Get the template path for the compiled views. */
    protected array $templatePath;
    /** @var string Get the compiled path for the compiled views. If null then it uses the default path */
    protected string $compiledPath;
    /** @var string the extension of the compiled file. */
    protected string $compileExtension = '.bladec';
    /**
     * @var string=['auto', 'sha1', 'md5', 'nochange'][$i] It determines how the compiled filename will be called.<br>
     *            <b>auto</b> (default mode) the mode is "sha1" unless the mode is MODE_DEBUG<br>
     *            <b>sha1</b> the filename is converted into a sha1 hash<br>
     *            <b>md5</b> the filename is converted into a md5 hash<br>
     *            <b>normal</b> the filename is left untouched<br>
     */
    protected string $compileTypeFileName='auto';


    /** @var array Custom "directive" dictionary. Those directives run at compile time. */
    protected array $customDirectives = [];
    /** @var bool[] Custom directive dictionary. Those directives run at runtime. */
    protected array $customDirectivesRT = [];
    /** @var callable Function used for resolving injected classes. */
    protected $injectResolver;
    /** @var array Used for conditional if. */
    protected array $conditions = [];
    /** @var int Unique counter. It's used for extends */
    protected int $uidCounter = 0;
    /** @var string The main url of the system. Don't use raw $_SERVER values unless the value is sanitized */
    protected string $baseUrl = '.';
    /** @var string|null The base domain of the system */
    protected ?string $baseDomain;
    /** @var string|null It stores the current canonical url. */
    protected ?string $canonicalUrl;
    /** @var string|null It stores the current url including arguments */
    protected ?string $currentUrl;
    /** @var string it is a relative path calculated between baseUrl and the current url. Example ../../ */
    protected string $relativePath = '';
    /** @var string[] Dictionary of assets */
    protected $assetDict;
    /** @var bool if true then it removes tabs and unneeded spaces */
    protected $optimize = true;
    /** @var bool if false, then the template is not compiled (but executed on memory). */
    protected $isCompiled = true;
    /** @var bool */
    protected $isRunFast = false; // stored for historical purpose.
    /** @var array Array of opening and closing tags for raw echos. */
    protected $rawTags = ['{!!', '!!}'];
    /** @var array Array of opening and closing tags for regular echos. */
    protected $contentTags = ['{{', '}}'];
    /** @var array Array of opening and closing tags for escaped echos. */
    protected $escapedTags = ['{{{', '}}}'];
    /** @var string The "regular" / legacy echo string format. */
    protected $echoFormat = '\htmlentities(%s??\'\', ENT_QUOTES, \'UTF-8\', false)';
    /** @var string */
    protected $echoFormatOld = 'static::e(%s)';
    /** @var array Lines that will be added at the footer of the template */
    protected $footer = [];
    /** @var string Placeholder to temporary mark the position of verbatim blocks. */
    protected $verbatimPlaceholder = '$__verbatim__$';
    /** @var array Array to temporary store the verbatim blocks found in the template. */
    protected $verbatimBlocks = [];
    /** @var int Counter to keep track of nested forelse statements. */
    protected $forelseCounter = 0;
    /** @var array The components being rendered. */
    protected $componentStack = [];
    /** @var array The original data passed to the component. */
    protected $componentData = [];
    /** @var array The slot contents for the component. */
    protected $slots = [];
    /** @var array The names of the slots being rendered. */
    protected $slotStack = [];
    /** @var string tag unique */
    protected $PARENTKEY = '@parentXYZABC';
    /**
     * Indicates the compile mode.
     * if the constant View_MODE is defined, then it is used instead of this field.
     *
     * @var int=[View::MODE_AUTO,View::MODE_DEBUG,View::MODE_SLOW,View::MODE_FAST][$i]
     */
    protected $mode;
    /** @var int Indicates the number of open switches */
    protected $switchCount = 0;
    /** @var bool Indicates if the switch is recently open */
    protected $firstCaseInSwitch = true;

    //</editor-fold>

    //<editor-fold desc="constructor">

    /**
     * Bob the constructor.
     * The folder at $compiledPath is created in case it doesn't exist.
     *
     * @param string|array $templatePath If null then it uses (caller_folder)/views
     * @param string       $compiledPath If null then it uses (caller_folder)/compiles
     * @param int          $mode         =[View::MODE_AUTO,View::MODE_DEBUG,View::MODE_FAST,View::MODE_SLOW][$i]
     */
    public function __construct($templatePath = null, $compiledPath = null, $mode = 0)
    {
        if ($templatePath === null) {
            $templatePath = getcwd() . '/views';
        }
        if ($compiledPath === null) {
            $compiledPath = getcwd() . '/compiles';
        }
        $this->templatePath = (is_array($templatePath)) ? $templatePath : [$templatePath];
        $this->compiledPath = $compiledPath;
        $this->setMode($mode);
        $this->authCallBack = function (
            $action = null,
            /** @noinspection PhpUnusedParameterInspection */
            $subject = null
        ) {
            return in_array($action, $this->currentPermission, true);
        };

        $this->authAnyCallBack = function ($array = []) {
            foreach ($array as $permission) {
                if (in_array($permission, $this->currentPermission ?? [], true)) {
                    return true;
                }
            }
            return false;
        };

        $this->errorCallBack = static function (
            /** @noinspection PhpUnusedParameterInspection */
            $key = null
        ) {
            return false;
        };


        // If the traits has "Constructors", then we call them.
        // Requisites.
        // 1- the method must be public or protected
        // 2- it must don't have arguments
        // 3- It must have the name of the trait. i.e. trait=MyTrait, method=MyTrait()
        $traits = get_declared_traits();
        foreach ($traits as $trait) {
            $r = explode('\\', $trait);
            $name = end($r);
            if (is_callable([$this, $name]) && method_exists($this, $name)) {
                $this->{$name}();
            }
        }
    }
    //</editor-fold>
    //<editor-fold desc="common">

    /**
     * Show an error in the web.
     *
     * @param string $id Title of the error
     * @param string $text Message of the error
     * @param bool $critic if true then the compilation is ended, otherwise it continues
     * @param bool $alwaysThrow if true then it always throws a runtime exception.
     * @return string
     * @throws RuntimeException
     */
    public function showError($id, $text, $critic = false, $alwaysThrow = false): string
    {
        ob_get_clean();
        if ($this->throwOnError || $alwaysThrow || $critic === true) {
            throw new RuntimeException("View Error [$id] $text");
        }

        $msg = "<div style='background-color: red; color: black; padding: 3px; border: solid 1px black;'>";
        $msg .= "View Error [$id]:<br>";
        $msg .= "<span style='color:white'>$text</span><br></div>\n";
        echo $msg;
        if ($critic) {
            die(1);
        }
        return $msg;
    }

    /**
     * Escape HTML entities in a string.
     *
     * @param int|string|null $value
     * @return string
     */
    public static function e(mixed $value): string
    {
        // Prevent "Deprecated: htmlentities(): Passing null to parameter #1 ($string) of type string is deprecated" message
        if (is_null($value)) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            return htmlentities(print_r($value, true), ENT_QUOTES, 'UTF-8', false);
        }
        if (is_numeric($value)) {
            $value = (string)$value;
        }
        return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
    }

    protected static function convertArgCallBack($k, $v): string
    {
        return $k . "='$v' ";
    }

    /**
     * @param mixed|DateTime $variable
     * @param string|null $format
     * @return string
     */
    public function format($variable, $format = null): string
    {
        if ($variable instanceof DateTime) {
            $format = $format ?? 'Y/m/d';
            return $variable->format($format);
        }
        $format = $format ?? '%s';
        return sprintf($format, $variable);
    }

    /**
     * It converts a text into a php code with echo<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->wrapPHP('$hello'); // "< ?php echo $this->e($hello); ? >"
     * $this->wrapPHP('$hello', ''); // < ?php echo $this->e($hello); ? >
     * $this->wrapPHP('$hello', '',false); // < ?php echo $hello; ? >
     * $this->wrapPHP('"hello"'); // "< ?php echo $this->e("hello"); ? >"
     * $this->wrapPHP('hello()'); // "< ?php echo $this->e(hello()); ? >"
     * </pre>
     *
     * @param ?string $input The input value
     * @param string $quote The quote used (to quote the result)
     * @param bool   $parse If the result will be parsed or not. If false then it's returned without $this->e
     * @return string
     */
    public function wrapPHP($input, $quote = '"', $parse = true): string
    {
        if($input===null) {
            return 'null';
        }
        if (strpos($input, '(') !== false && !$this->isQuoted($input)) {
            if ($parse) {
                return $quote . $this->phpTagEcho . '$this->e(' . $input . ');?>' . $quote;
            }

            return $quote . $this->phpTagEcho . $input . ';?>' . $quote;
        }
        if (strpos($input, '$') === false) {
            if ($parse) {
                return self::enq($input);
            }

            return $input;
        }
        if ($parse) {
            return $quote . $this->phpTagEcho . '$this->e(' . $input . ');?>' . $quote;
        }
        return $quote . $this->phpTagEcho . $input . ';?>' . $quote;
    }

    /**
     * Returns true if the text is surrounded by quotes (double or single quote)
     *
     * @param string|null $text
     * @return bool
     */
    public function isQuoted($text): bool
    {
        if (!$text || strlen($text) < 2) {
            return false;
        }
        if ($text[0] === '"' && substr($text, -1) === '"') {
            return true;
        }
        return ($text[0] === "'" && substr($text, -1) === "'");
    }

    /**
     * Escape HTML entities in a string.
     *
     * @param string $value
     * @return string
     */
    public static function enq(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return htmlentities(print_r($value, true), ENT_NOQUOTES, 'UTF-8', false);
        }
        return htmlentities($value ?? '', ENT_NOQUOTES, 'UTF-8', false);
    }

    /**
     * @param string      $view  example "folder.template"
     * @param string|null $alias example "mynewop". If null then it uses the name of the template.
     */
    public function addInclude(string $view, string|null|array|object $alias = null): void
    {
        if (!isset($alias)) {
            $alias = explode('.', $view);
            $alias = end($alias);
        }
        $this->directive($alias, function ($expression) use ($view) {
            $expression = $this->stripParentheses($expression) ?: '[]';
            return "$this->phpTag echo \$this->runChild('$view', $expression); ?>";
        });
    }

    /**
     * Register a handler for custom directives.
     *
     * @param string   $name
     * @param callable $handler
     * @return void
     */
    public function directive($name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
        $this->customDirectivesRT[$name] = false;
    }

    /**
     * Strip the parentheses from the given expression.
     *
     * @param string|null $expression
     * @return string
     */
    public function stripParentheses($expression): string
    {
        if (is_null($expression)) {
            return '';
        }

        if (static::startsWith($expression, '(')) {
            $expression = substr($expression, 1, -1);
        }

        return $expression;
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param string       $haystack
     * @param string|array $needles
     * @return bool
     */
    public static function startsWith($haystack, $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle != '') {
                if (function_exists('mb_strpos')) {
                    if ($haystack !== null && mb_strpos($haystack, $needle) === 0) {
                        return true;
                    }
                } elseif ($haystack !== null && strpos($haystack, $needle) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * If false then the file is not compiled, and it is executed directly from the memory.<br>
     * By default the value is true<br>
     * It also sets the mode to MODE_SLOW
     *
     * @param bool $bool
     * @return View
     * @see \Sura\View\View::setMode
     */
    public function setIsCompiled($bool = false): View
    {
        $this->isCompiled = $bool;
        if (!$bool) {
            $this->setMode(self::MODE_SLOW);
        }
        return $this;
    }

    /**
     * It sets the template and compile path (without trailing slash).
     * <p>Example:setPath("somefolder","otherfolder");
     *
     * @param null|string|string[] $templatePath If null then it uses the current path /views folder
     * @param null|string          $compiledPath If null then it uses the current path /views folder
     */
    public function setPath($templatePath, $compiledPath): void
    {
        if ($templatePath === null) {
            $templatePath = getcwd() . '/views';
        }
        if ($compiledPath === null) {
            $compiledPath = getcwd() . '/compiles';
        }
        $this->templatePath = (is_array($templatePath)) ? $templatePath : [$templatePath];
        $this->compiledPath = $compiledPath;
    }

    /**
     * @return array
     */
    public function getAliasClasses(): array
    {
        return $this->aliasClasses;
    }

    /**
     * @param array $aliasClasses
     */
    public function setAliasClasses($aliasClasses): void
    {
        $this->aliasClasses = $aliasClasses;
    }

    /**
     * @param string $aliasName
     * @param string $classWithNS
     */
    public function addAliasClasses($aliasName, $classWithNS): void
    {
        $this->aliasClasses[$aliasName] = $classWithNS;
    }
    //</editor-fold>
    //<editor-fold desc="compile">

    /**
     * Authentication. Sets with a user,role and permission
     *
     * @param string $user
     * @param null   $role
     * @param array  $permission
     */
    public function setAuth($user = '', $role = null, $permission = []): void
    {
        $this->currentUser = $user;
        $this->currentRole = $role;
        $this->currentPermission = $permission;
    }

    /**
     * run the blade engine. It returns the result of the code.
     *
     * @param string $string HTML to parse
     * @param array $data It is an associative array with the datas to display.
     * @return string It returns a parsed string
     * @throws Exception
     */
    public function runString($string, $data = []): string
    {
        $php = $this->compileString($string);

        $obLevel = ob_get_level();
        ob_start();
        extract($data, EXTR_SKIP);

        $previousError = error_get_last();

        try {
            @eval('?' . '>' . $php);
        } catch (Exception $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            throw $e;
        } catch (ParseError $e) { // PHP >= 7
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            $this->showError('runString', $e->getMessage() . ' ' . $e->getCode(), true);
            return '';
        }

        $lastError = error_get_last(); // PHP 5.6
        if ($previousError != $lastError && $lastError['type'] == E_PARSE) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            $this->showError('runString', $lastError['message'] . ' ' . $lastError['type'], true);
            return '';
        }

        return ob_get_clean();
    }

    /**
     * Compile the given Blade template contents.
     *
     * @param string $value
     * @return string
     */
    public function compileString($value): string
    {
        $result = '';
        if (strpos($value, '@verbatim') !== false) {
            $value = $this->storeVerbatimBlocks($value);
        }
        $this->footer = [];
        // Here we will loop through all the tokens returned by the Zend lexer and
        // parse each one into the corresponding valid PHP. We will then have this
        // template as the correctly rendered PHP that can be rendered natively.
        foreach (token_get_all($value) as $token) {
            $result .= is_array($token) ? $this->parseToken($token) : $token;
        }
        if (!empty($this->verbatimBlocks)) {
            $result = $this->restoreVerbatimBlocks($result);
        }
        // If there are any footer lines that need to get added to a template we will
        // add them here at the end of the template. This gets used mainly for the
        // template inheritance via the extends keyword that should be appended.
        if (count($this->footer) > 0) {
            $result = ltrim($result, PHP_EOL)
                . PHP_EOL . implode(PHP_EOL, array_reverse($this->footer));
        }
        return $result;
    }

    /**
     * Store the verbatim blocks and replace them with a temporary placeholder.
     *
     * @param string $value
     * @return string
     */
    protected function storeVerbatimBlocks($value): string
    {
        return preg_replace_callback('/(?<!@)@verbatim(.*?)@endverbatim/s', function ($matches) {
            $this->verbatimBlocks[] = $matches[1];
            return $this->verbatimPlaceholder;
        }, $value);
    }

    /**
     * Parse the tokens from the template.
     *
     * @param array $token
     *
     * @return string
     *
     * @see \Sura\View\View::compileStatements
     * @see \Sura\View\View::compileExtends
     * @see \Sura\View\View::compileComments
     * @see \Sura\View\View::compileEchos
     */
    protected function parseToken($token): string
    {
        [$id, $content] = $token;
        if ($id == T_INLINE_HTML) {
            foreach ($this->compilers as $type) {
                $content = $this->{"compile$type"}($content);
            }
        }
        return $content;
    }

    /**
     * Replace the raw placeholders with the original code stored in the raw blocks.
     *
     * @param string $result
     * @return string
     */
    protected function restoreVerbatimBlocks($result): string
    {
        $result = preg_replace_callback('/' . preg_quote($this->verbatimPlaceholder) . '/', function () {
            return array_shift($this->verbatimBlocks);
        }, $result);
        $this->verbatimBlocks = [];
        return $result;
    }

    /**
     * it calculates the relative path of a web.<br>
     * This function uses the current url and the baseurl
     *
     * @param string $relativeWeb . Example img/images.jpg
     * @return string  Example ../../img/images.jpg
     */
    public function relative($relativeWeb): string
    {
        return $this->assetDict[$relativeWeb] ?? ($this->relativePath . $relativeWeb);
    }

    /**
     * It adds an alias to the link of the resources.<br>
     * addAssetDict('name', 'url/res.jpg')<br>
     * addAssetDict(['name'=>'url/res.jpg', 'name2'=>'url/res2.jpg']);
     *
     * @param string|array $name example 'css/style.css', you could also add an array
     * @param string       $url  example https://www.web.com/style.css'
     */
    public function addAssetDict($name, $url = ''): void
    {
        if (is_array($name)) {
            if ($this->assetDict === null) {
                $this->assetDict = $name;
            } else {
                $this->assetDict = array_merge($this->assetDict, $name);
            }
        } else {
            $this->assetDict[$name] = $url;
        }
    }

    /**
     * Start injecting content into a push section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    public function startPush($section, $content = ''): void
    {
        if ($content === '') {
            if (ob_start()) {
                $this->pushStack[] = $section;
            }
        } else {
            $this->extendPush($section, $content);
        }
    }

    /*
     * endswitch tag
     */

    /**
     * Append content to a given push section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    protected function extendPush($section, $content): void
    {
        if (!isset($this->pushes[$section])) {
            $this->pushes[$section] = []; // start an empty section
        }
        if (!isset($this->pushes[$section][$this->renderCount])) {
            $this->pushes[$section][$this->renderCount] = $content;
        } else {
            $this->pushes[$section][$this->renderCount] .= $content;
        }
    }

    /**
     * Start injecting content into a push section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    public function startPrepend($section, $content = ''): void
    {
        if ($content === '') {
            if (ob_start()) {
                array_unshift($this->pushStack[], $section);
            }
        } else {
            $this->extendPush($section, $content);
        }
    }

    /**
     * Stop injecting content into a push section.
     *
     * @return string
     */
    public function stopPush(): string
    {
        if (empty($this->pushStack)) {
            $this->showError('stopPush', 'Cannot end a section without first starting one', true);
        }
        $last = array_pop($this->pushStack);
        $this->extendPush($last, ob_get_clean());
        return $last;
    }

    /**
     * Stop injecting content into a push section.
     *
     * @return string
     */
    public function stopPrepend(): string
    {
        if (empty($this->pushStack)) {
            $this->showError('stopPrepend', 'Cannot end a section without first starting one', true);
        }
        $last = array_shift($this->pushStack);
        $this->extendStartPush($last, ob_get_clean());
        return $last;
    }

    /**
     * Append content to a given push section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    protected function extendStartPush($section, $content): void
    {
        if (!isset($this->pushes[$section])) {
            $this->pushes[$section] = []; // start an empty section
        }
        if (!isset($this->pushes[$section][$this->renderCount])) {
            $this->pushes[$section][$this->renderCount] = $content;
        } else {
            $this->pushes[$section][$this->renderCount] = $content . $this->pushes[$section][$this->renderCount];
        }
    }

    /**
     * Get the string contents of a push section.
     *
     * @param string $section
     * @param string $default
     * @return string
     */
    public function yieldPushContent($section, $default = ''): string
    {
        if (!isset($this->pushes[$section])) {
            return $default;
        }
        return implode(array_reverse($this->pushes[$section]));
    }

    /**
     * Get the string contents of a push section.
     *
     * @param int|string $each if "int", then it split the foreach every $each numbers.<br>
     *                         if "string" or "c3", then it means that it will split in 3 columns<br>
     * @param string     $splitText
     * @param string     $splitEnd
     * @return string
     */
    public function splitForeach($each = 1, $splitText = ', ', $splitEnd = ''): string
    {
        $loopStack = static::last($this->loopsStack); // array(7) { ["index"]=> int(0) ["remaining"]=> int(6) ["count"]=> int(5) ["first"]=> bool(true) ["last"]=> bool(false) ["depth"]=> int(1) ["parent"]=> NULL }
        if (($loopStack['index']) == $loopStack['count'] - 1) {
            return $splitEnd;
        }
        $eachN = 0;
        if (is_numeric($each)) {
            $eachN = $each;
        } elseif (strlen($each) > 1) {
            if ($each[0] === 'c') {
                $eachN = $loopStack['count'] / substr($each, 1);
            }
        } else {
            $eachN = PHP_INT_MAX;
        }

        if (($loopStack['index'] + 1) % $eachN === 0) {
            return $splitText;
        }
        return '';
    }

    /**
     * Return the last element in an array passing a given truth test.
     *
     * @param array         $array
     * @param callable|null $callback
     * @param mixed         $default
     * @return mixed
     */
    public static function last($array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($array) ? static::value($default) : end($array);
        }
        return static::first(array_reverse($array), $callback, $default);
    }

    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }

    /**
     * Return the first element in an array passing a given truth test.
     *
     * @param array         $array
     * @param callable|null $callback
     * @param mixed         $default
     * @return mixed
     */
    public static function first($array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($array) ? static::value($default) : reset($array);
        }
        foreach ($array as $key => $value) {
            if ($callback($key, $value)) {
                return $value;
            }
        }
        return static::value($default);
    }

    /**
     * @param string $name
     * @param        $args []
     * @return string
     * @throws BadMethodCallException
     */
    public function __call($name, $args)
    {
        if ($name === 'if') {
            return $this->registerIfStatement($args[0] ?? null, $args[1] ?? null);
        }
        $this->showError('call', "function $name is not defined<br>", true, true);
        return '';
    }

    /**
     * Register an "if" statement directive.
     *
     * @param string   $name
     * @param callable $callback
     * @return string
     */
    public function registerIfStatement($name, callable $callback): string
    {
        $this->conditions[$name] = $callback;

        $this->directive($name, function ($expression) use ($name) {
            $tmp = $this->stripParentheses($expression);
            return $expression !== ''
                ? $this->phpTag . " if (\$this->check('$name', $tmp)): ?>"
                : $this->phpTag . " if (\$this->check('$name')): ?>";
        });

        $this->directive('else' . $name, function ($expression) use ($name) {
            $tmp = $this->stripParentheses($expression);
            return $expression !== ''
                ? $this->phpTag . " elseif (\$this->check('$name', $tmp)): ?>"
                : $this->phpTag . " elseif (\$this->check('$name')): ?>";
        });

        $this->directive('end' . $name, function () {
            return $this->phpTag . ' endif; ?>';
        });
        return '';
    }

    /**
     * Check the result of a condition.
     *
     * @param string $name
     * @param array  $parameters
     * @return bool
     */
    public function check($name, ...$parameters): bool
    {
        return call_user_func($this->conditions[$name], ...$parameters);
    }

    /**
     * @param bool   $bool
     * @param string $view  name of the view
     * @param array  $value arrays of values
     * @return string
     * @throws Exception
     */
    public function includeWhen($bool = false, $view = '', $value = []): string
    {
        if ($bool) {
            return $this->runChild($view, $value);
        }
        return '';
    }

    /**
     * Macro of function run
     *
     * @param       $view
     * @param array $variables
     * @return string
     * @throws Exception
     */
    public function runChild($view, $variables = []): string
    {
        if (is_array($variables)) {
            if ($this->includeScope) {
                $backup = $this->variables;
            } else {
                $backup = null;
            }
            $newVariables = array_merge($this->variables, $variables);
        } else {
            if ($variables === null) {
                $newVariables = $this->variables;
                var_dump($newVariables);
                die(1);
            }

            $this->showError('run/include', "RunChild: Include/run variables should be defined as array ['idx'=>'value']", true);
            return '';
        }
        $r = $this->runInternal($view, $newVariables, false, false, $this->isRunFast);
        if ($backup !== null) {
            $this->variables = $backup;
        }
        return $r;
    }

    /**
     * run the blade engine. It returns the result of the code.
     *
     * @param string $view
     * @param array  $variables
     * @param bool   $forced  if true then it recompiles no matter if the compiled file exists or not.
     * @param bool   $isParent
     * @param bool   $runFast if true then the code is not compiled neither checked, and it runs directly the compiled
     *                        version.
     * @return string
     * @throws Exception
     * @noinspection PhpUnusedParameterInspection
     */
    protected function runInternal($view, $variables = [], $forced = false, $isParent = true, $runFast = false): string
    {
        $this->currentView = $view;
        if (@count($this->composerStack)) {
            $this->evalComposer($view);
        }
        if (@count($this->variablesGlobal) > 0) {
            $this->variables = array_merge($variables, $this->variablesGlobal);
            //$this->variablesGlobal = []; // used so we delete it.
        } else {
            $this->variables = $variables;
        }
        if (!$runFast) {
            // a) if the "compile" is forced then we compile the original file, then save the file.
            // b) if the "compile" is not forced then we read the datetime of both file, and we compared.
            // c) in both cases, if the compiled doesn't exist then we compile.
            if ($view) {
                $this->fileName = $view;
            }
            $result = $this->compile($view, $forced);
            if (!$this->isCompiled) {
                return $this->evaluateText($result, $this->variables);
            }
        } elseif ($view) {
            $this->fileName = $view;
        }
        $this->isRunFast = $runFast;
        return $this->evaluatePath($this->getCompiledFile(), $this->variables);
    }

    protected function evalComposer($view): void
    {
        foreach ($this->composerStack as $viewKey => $fn) {
            if ($this->wildCardComparison($view, $viewKey)) {
                if (is_callable($fn)) {
                    $fn($this);
                } elseif ($this->methodExistsStatic($fn, 'composer')) {
                    // if the method exists statically then $fn is the class and 'composer' is the name of the method
                    $fn::composer($this);
                } elseif (is_object($fn) || class_exists($fn)) {
                    // if $fn is an object, or it is a class and the class exists.
                    $instance = (is_object($fn)) ? $fn : new $fn();
                    if (method_exists($instance, 'composer')) {
                        // and the method exists inside the instance.
                        $instance->composer($this);
                    } else {
                        if ($this->mode === self::MODE_DEBUG) {
                            $this->showError('evalComposer', "View: composer() added an incorrect method [$fn]", true, true);
                            return;
                        }
                        $this->showError('evalComposer', 'View: composer() added an incorrect method', true, true);
                        return;
                    }
                } else {
                    $this->showError('evalComposer', 'View: composer() added an incorrect method', true, true);
                }
            }
        }
    }

    /**
     * It compares with wildcards (*) and returns true if both strings are equals<br>
     * The wildcards only works at the beginning and/or at the end of the string.<br>
     * <b>Example:<b><br>
     * <pre>
     * Text::wildCardComparison('abcdef', 'abc*'); // true
     * Text::wildCardComparison('abcdef', '*def'); // true
     * Text::wildCardComparison('abcdef', '*abc*'); // true
     * Text::wildCardComparison('abcdef', '*cde*'); // true
     * Text::wildCardComparison('abcdef', '*cde'); // false
     *
     * </pre>
     *
     * @param string      $text
     * @param string|null $textWithWildcard
     *
     * @return bool
     */
    protected function wildCardComparison($text, $textWithWildcard): bool
    {
        if (($textWithWildcard === null || $textWithWildcard === '')
            || strpos($textWithWildcard, '*') === false
        ) {
            // if the text with wildcard is null or empty, or it contains two ** or it contains no * then..
            return $text == $textWithWildcard;
        }
        if ($textWithWildcard === '*' || $textWithWildcard === '**') {
            return true;
        }
        $c0 = $textWithWildcard[0];
        $c1 = substr($textWithWildcard, -1);
        $textWithWildcardClean = str_replace('*', '', $textWithWildcard);
        $p0 = strpos($text, $textWithWildcardClean);
        if ($p0 === false) {
            // no matches.
            return false;
        }
        if ($c0 === '*' && $c1 === '*') {
            // $textWithWildcard='*asasasas*'
            return true;
        }
        if ($c1 === '*') {
            // $textWithWildcard='asasasas*'
            return $p0 === 0;
        }
        // $textWithWildcard='*asasasas'
        $len = strlen($textWithWildcardClean);
        return (substr($text, -$len) === $textWithWildcardClean);
    }

    protected function methodExistsStatic($class, $method): bool
    {
        try {
            return (new ReflectionMethod($class, $method))->isStatic();
        } catch (ReflectionException $e) {
            return false;
        }
    }

    /**
     * Compile the view at the given path.
     *
     * @param string $templateName The name of the template. Example folder.template
     * @param bool   $forced       If the compilation will be forced (always compile) or not.
     * @return boolean|string True if the operation was correct, or false (if not exception)
     *                             if it fails. It returns a string (the content compiled) if isCompiled=false
     * @throws Exception
     */
    public function compile($templateName = null, $forced = false)
    {
        $compiled = $this->getCompiledFile($templateName);
        $template = $this->getTemplateFile($templateName);
        if (!$this->isCompiled) {
            $contents = $this->compileString($this->getFile($template));
            $this->compileCallBacks($contents, $templateName);
            return $contents;
        }
        if ($forced || $this->isExpired($templateName)) {
            // compile the original file
            $contents = $this->compileString($this->getFile($template));
            $this->compileCallBacks($contents, $templateName);
            if ($this->optimize) {
                // removes space and tabs and replaces by a single space
                $contents = preg_replace('/^ {2,}/m', ' ', $contents);
                $contents = preg_replace('/^\t{2,}/m', ' ', $contents);
            }
            $ok = file_put_contents($compiled, $contents);
            if ($ok === false) {
                $this->showError(
                    'Compiling',
                    "Unable to save the file [$compiled]. Check the compile folder is defined and has the right permission"
                );
                return false;
            }
        }
        return true;
    }

    /**
     * Get the full path of the compiled file.
     *
     * @param string $templateName
     * @return string
     */
    public function getCompiledFile($templateName = ''): string
    {
        $templateName = (empty($templateName)) ? $this->fileName : $templateName;
        $style=$this->compileTypeFileName;
        if ($style==='auto') {
            $style=($this->getMode() === self::MODE_DEBUG)?'nochange':'sha1';
        }
        switch ($style) {
            case 'normal':
                return $this->compiledPath . '/' . $templateName . $this->compileExtension;
            case 'md5':
                return $this->compiledPath . '/' . md5($templateName) . $this->compileExtension;
            case 'sha1':
                return $this->compiledPath . '/' . sha1($templateName) . $this->compileExtension;
        }
        return $this->compiledPath . '/' . $templateName . $this->compileExtension;
    }



    /**
     * Get the mode of the engine.See View::MODE_* constants
     *
     * @return int=[self::MODE_AUTO,self::MODE_DEBUG,self::MODE_FAST,self::MODE_SLOW][$i]
     */
    public function getMode(): int
    {
        if (defined('VIEW_MODE')) {
            $this->mode = VIEW_MODE;
        }
        return $this->mode;
    }

    /**
     * Set the compile mode<br>
     *
     * @param $mode int=[self::MODE_AUTO,self::MODE_DEBUG,self::MODE_FAST,self::MODE_SLOW][$i]
     * @return void
     */
    public function setMode($mode): void
    {
        $this->mode = $mode;
    }

    /**
     * Get the full path of the template file.
     * <p>Example: getTemplateFile('.abc.def')</p>
     *
     * @param string $templateName template name. If not template is set then it uses the base template.
     * @return string
     */
    public function getTemplateFile($templateName = ''): string
    {
        $templateName = (empty($templateName)) ? $this->fileName : $templateName;
        if (str_contains($templateName, '/')) {
            return $this->locateTemplate($templateName); // it's a literal
        }
        $arr = explode('.', $templateName);
        $c = count($arr);
        if ($c === 1) {
            // it's in the root of the template folder.
            return $this->locateTemplate($templateName . $this->fileExtension);
        }

        $file = $arr[$c - 1];
        array_splice($arr, $c - 1, $c - 1); // delete the last element
        $path = implode('/', $arr);
        return $this->locateTemplate($path . '/' . $file . $this->fileExtension);
    }

    /**
     * Find template file with the given name in all template paths in the order the paths were written
     *
     * @param string $name Filename of the template (without path)
     * @return string template file
     */
    protected function locateTemplate($name): string
    {
        $this->notFoundPath = '';
        foreach ($this->templatePath as $dir) {
            $path = $dir . '/' . $name;
            if (is_file($path)) {
                return $path;
            }

            $this->notFoundPath .= $path . ",";
        }
        return '';
    }

    /**
     * Get the contents of a file.
     *
     * @param string $fullFileName It gets the content of a filename or returns ''.
     *
     * @return string
     */
    public function getFile($fullFileName): string
    {
        if (is_file($fullFileName)) {
            return file_get_contents($fullFileName);
        }
        $this->showError('getFile', "File does not exist at paths (separated by comma) [$this->notFoundPath] or permission denied");
        return '';
    }

    protected function compileCallBacks(&$contents, $templateName): void
    {
        if (!empty($this->compileCallbacks)) {
            foreach ($this->compileCallbacks as $callback) {
                if (is_callable($callback)) {
                    $callback($contents, $templateName);
                }
            }
        }
    }

    /**
     * Determine if the view has expired.
     *
     * @param string|null $fileName
     * @return bool
     */
    public function isExpired($fileName): bool
    {
        $compiled = $this->getCompiledFile($fileName);
        $template = $this->getTemplateFile($fileName);
        if (!is_file($template)) {
            if ($this->mode == self::MODE_DEBUG) {
                $this->showError('Read file', 'Template not found :' . $this->fileName . " on file: $template", true);
            } else {
                $this->showError('Read file', 'Template not found :' . $this->fileName, true);
            }
        }
        // If the compiled file doesn't exist we will indicate that the view is expired
        // so that it can be re-compiled. Else, we will verify the last modification
        // of the views is less than the modification times of the compiled views.
        if (!$this->compiledPath || !is_file($compiled)) {
            return true;
        }
        return filemtime($compiled) < filemtime($template);
    }

    /**
     * Evaluates a text (string) using the current variables
     *
     * @param string $content
     * @param array  $variables
     * @return string
     * @throws Exception
     */
    protected function evaluateText($content, $variables): string
    {
        ob_start();
        extract($variables);
        // We'll evaluate the contents of the view inside a try/catch block, so we can
        // flush out any stray output that might get out before an error occurs or
        // an exception is thrown. This prevents any partial views from leaking.
        try {
            eval(' ?>' . $content . $this->phpTag);
        } catch (Exception $e) {
            $this->handleViewException($e);
        }
        return ltrim(ob_get_clean());
    }

    /**
     * Handle a view exception.
     *
     * @param Exception $e
     * @return void
     * @throws $e
     */
    protected function handleViewException($e): void
    {
        ob_get_clean();
        throw $e;
    }

    /**
     * Evaluates a compiled file using the current variables
     *
     * @param string $compiledFile full path of the compile file.
     * @param array  $variables
     * @return string
     * @throws Exception
     */
    protected function evaluatePath($compiledFile, $variables): string
    {
        ob_start();
        // note, the variables are extracted locally inside this method,
        // they are not global variables :-3
        extract($variables);
        // We'll evaluate the contents of the view inside a try/catch block, so we can
        // flush out any stray output that might get out before an error occurs or
        // an exception is thrown. This prevents any partial views from leaking.
        try {
            include $compiledFile;
        } catch (Exception $e) {
            $this->handleViewException($e);
        }
        return ltrim(ob_get_clean());
    }

    /**
     * @param array $views array of views
     * @param array $value
     * @return string
     * @throws Exception
     */
    public function includeFirst($views = [], $value = []): string
    {
        foreach ($views as $view) {
            if ($this->templateExist($view)) {
                return $this->runChild($view, $value);
            }
        }
        return '';
    }

    /**
     * Returns true if the template exists. Otherwise, it returns false
     *
     * @param $templateName
     * @return bool
     */
    protected function templateExist($templateName): bool
    {
        $file = $this->getTemplateFile($templateName);
        return is_file($file);
    }

    /**
     * Convert an array such as ["class1"=>"myclass","style="mystyle"] to class1='myclass' style='mystyle' string
     *
     * @param array|string $array array to convert
     * @return string
     */
    public function convertArg($array): string
    {
        if (!is_array($array)) {
            return $array;  // nothing to convert.
        }
        return implode(' ', array_map('static::convertArgCallBack', array_keys($array), $array));
    }

    /**
     * Returns the current token. if there is not a token then it generates a new one.
     * It could require an open session.
     *
     * @param bool   $fullToken It returns a token with the current ip.
     * @param string $tokenId   [optional] Name of the token.
     *
     * @return string
     */
    public function getCsrfToken($fullToken = false, $tokenId = '_token'): string
    {
        if ($this->csrf_token == '') {
            $this->regenerateToken($tokenId);
        }
        if ($fullToken) {
            return $this->csrf_token . '|' . $this->ipClient();
        }
        return $this->csrf_token;
    }

    /**
     * Regenerates the csrf token and stores in the session.
     * It requires an open session.
     *
     * @param string $tokenId [optional] Name of the token.
     */
    public function regenerateToken($tokenId = '_token'): void
    {
        try {
            $this->csrf_token = bin2hex(random_bytes(10));
        } catch (Exception $e) {
            $this->csrf_token = '123456789012345678901234567890'; // unable to generates a random token.
        }
        $_SESSION[$tokenId] = $this->csrf_token . '|' . $this->ipClient();
    }

    public function ipClient()
    {
        if (
            isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            && preg_match('/^(d{1,3}).(d{1,3}).(d{1,3}).(d{1,3})$/', $_SERVER['HTTP_X_FORWARDED_FOR'])
        ) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Validates if the csrf token is valid or not.<br>
     * It requires an open session.
     *
     * @param bool   $alwaysRegenerate [optional] Default is false.<br>
     *                                 If <b>true</b> then it will generate a new token regardless
     *                                 of the method.<br>
     *                                 If <b>false</b>, then it will generate only if the method is POST.<br>
     *                                 Note: You must not use true if you want to use csrf with AJAX.
     *
     * @param string $tokenId          [optional] Name of the token.
     *
     * @return bool It returns true if the token is valid, or it is generated. Otherwise, false.
     */
    public function csrfIsValid($alwaysRegenerate = false, $tokenId = '_token'): bool
    {
        if (@$_SERVER['REQUEST_METHOD'] === 'POST' && $alwaysRegenerate === false) {
            $this->csrf_token = $_POST[$tokenId] ?? null; // ping pong the token.
            return $this->csrf_token . '|' . $this->ipClient() === ($_SESSION[$tokenId] ?? null);
        }

        if ($this->csrf_token == '' || $alwaysRegenerate) {
            // if not token then we generate a new one
            $this->regenerateToken($tokenId);
        }
        return true;
    }

    /**
     * Stop injecting content into a section and return its contents.
     *
     * @return string
     */
    public function yieldSection(): ?string
    {
        $sc = $this->stopSection();
        return $this->sections[$sc] ?? null;
    }

    /**
     * Stop injecting content into a section.
     *
     * @param bool $overwrite
     * @return string
     */
    public function stopSection($overwrite = false): string
    {
        if (empty($this->sectionStack)) {
            $this->showError('stopSection', 'Cannot end a section without first starting one.', true, true);
        }
        $last = array_pop($this->sectionStack);
        if ($overwrite) {
            $this->sections[$last] = ob_get_clean();
        } else {
            $this->extendSection($last, ob_get_clean());
        }
        return $last;
    }

    /**
     * Append content to a given section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    protected function extendSection($section, $content): void
    {
        if (isset($this->sections[$section])) {
            $content = str_replace($this->PARENTKEY, $content, $this->sections[$section]);
        }
        $this->sections[$section] = $content;
    }

    public function dump($object, $jsconsole = false): void
    {
        if (!$jsconsole) {
            echo '<pre>';
            var_dump($object);
            echo '</pre>';
        } else {
            /** @noinspection BadExpressionStatementJS */
            /** @noinspection JSVoidFunctionReturnValueUsed */
            echo '<script>console.log(' . json_encode($object) . ')</script>';
        }
    }

    /**
     * Start injecting content into a section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    public function startSection($section, $content = ''): void
    {
        if ($content === '') {
            ob_start() && $this->sectionStack[] = $section;
        } else {
            $this->extendSection($section, $content);
        }
    }

    /**
     * Stop injecting content into a section and append it.
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public function appendSection(): string
    {
        if (empty($this->sectionStack)) {
            $this->showError('appendSection', 'Cannot end a section without first starting one.', true, true);
        }
        $last = array_pop($this->sectionStack);
        if (isset($this->sections[$last])) {
            $this->sections[$last] .= ob_get_clean();
        } else {
            $this->sections[$last] = ob_get_clean();
        }
        return $last;
    }

    /**
     * Adds a global variable. If <b>$varname</b> is an array then it merges all the values.
     * <b>Example:</b>
     * <pre>
     * $this->share('variable',10.5);
     * $this->share('variable2', 'hello');
     * // or we could add the two variables as:
     * $this->share(['variable'=>10.5, 'variable2'=>'hello']);
     * </pre>
     *
     * @param string|array $varname It is the name of the variable or, it is an associative array
     * @param mixed        $value
     * @return $this
     * @see \Sura\View\View::share
     */
    public function with($varname, $value = null): View
    {
        return $this->share($varname, $value);
    }

    /**
     * Adds a global variable. If <b>$varname</b> is an array then it merges all the values.
     * <b>Example:</b>
     * <pre>
     * $this->share('variable',10.5);
     * $this->share('variable2', 'hello');
     * // or we could add the two variables as:
     * $this->share(['variable'=>10.5, 'variable2'=>'hello']);
     * </pre>
     *
     * @param string|array $varname It is the name of the variable, or it is an associative array
     * @param mixed        $value
     * @return $this
     */
    public function share($varname, $value = null): View
    {
        if (is_array($varname)) {
            $this->variablesGlobal = array_merge($this->variablesGlobal, $varname);
        } else {
            $this->variablesGlobal[$varname] = $value;
        }
        return $this;
    }

    /**
     * Get the string contents of a section.
     *
     * @param string $section
     * @param string $default
     * @return string
     */
    public function yieldContent($section, $default = ''): string
    {
        if (isset($this->sections[$section])) {
            return str_replace($this->PARENTKEY, $default, $this->sections[$section]);
        }

        return $default;
    }

    /**
     * Register a custom Blade compiler.
     *
     * @param callable $compiler
     * @return void
     */
    public function extend(callable $compiler): void
    {
        $this->extensions[] = $compiler;
    }

    /**
     * Register a handler for custom directives for run at runtime
     *
     * @param string   $name
     * @param callable $handler
     * @return void
     */
    public function directiveRT($name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
        $this->customDirectivesRT[$name] = true;
    }

    /**
     * Sets the escaped content tags used for the compiler.
     *
     * @param string $openTag
     * @param string $closeTag
     * @return void
     */
    public function setEscapedContentTags($openTag, $closeTag): void
    {
        $this->setContentTags($openTag, $closeTag, true);
    }

    /**
     * Gets the content tags used for the compiler.
     *
     * @return array
     */
    public function getContentTags(): array
    {
        return $this->getTags();
    }

    /**
     * Sets the content tags used for the compiler.
     *
     * @param string $openTag
     * @param string $closeTag
     * @param bool   $escaped
     * @return void
     */
    public function setContentTags($openTag, $closeTag, $escaped = false): void
    {
        $property = ($escaped === true) ? 'escapedTags' : 'contentTags';
        $this->{$property} = [preg_quote($openTag), preg_quote($closeTag)];
    }

    /**
     * Gets the tags used for the compiler.
     *
     * @param bool $escaped
     * @return array
     */
    protected function getTags($escaped = false): array
    {
        $tags = $escaped ? $this->escapedTags : $this->contentTags;
        return array_map('stripcslashes', $tags);
    }

    /**
     * Gets the escaped content tags used for the compiler.
     *
     * @return array
     */
    public function getEscapedContentTags(): array
    {
        return $this->getTags(true);
    }

    /**
     * Sets the function used for resolving classes with inject.
     *
     * @param callable $function
     */
    public function setInjectResolver(callable $function): void
    {
        $this->injectResolver = $function;
    }

    /**
     * Get the file extension for template files.
     *
     * @return string
     */
    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    /**
     * Set the file extension for the template files.
     * It must include the leading dot e.g. ".blade.php"
     *
     * @param string $fileExtension Example: .prefix.ext
     */
    public function setFileExtension($fileExtension): void
    {
        $this->fileExtension = $fileExtension;
    }

    /**
     * Get the file extension for template files.
     *
     * @return string
     */
    public function getCompiledExtension(): string
    {
        return $this->compileExtension;
    }

    /**
     * Set the file extension for the compiled files.
     * Including the leading dot for the extension is required, e.g. ".bladec"
     *
     * @param $fileExtension
     */
    public function setCompiledExtension($fileExtension): void
    {
        $this->compileExtension = $fileExtension;
    }
    /**
     * @return string
     * @see \Sura\View\View::setCompileTypeFileName
     */
    public function getCompileTypeFileName()
    {
        return $this->compileTypeFileName;
    }

    /**
     * It determines how the compiled filename will be called.<br>
     * <b>auto</b> (default mode) the mode is "sha1" unless the mode is MODE_DEBUG<br>
     * <b>sha1</b> the filename is converted into a sha1 hash (it's the slow method, but it is safest)<br>
     * <b>md5</b> the filename is converted into a md5 hash (it's faster than sha1, and it uses less space)<br>
     * <b>normal</b> the filename is left untouched (it's the fastest mode but the filename could be exposed)<br>
     * @param string $compileTypeFileName=['auto', 'sha1', 'md5', 'nochange'][$i]
     * @return View
     */
    public function setCompileTypeFileName(string $compileTypeFileName): View
    {
        $this->compileTypeFileName = $compileTypeFileName;
        return $this;
    }
    /**
     * Add new loop to the stack.
     *
     * @param array|Countable $data
     * @return void
     */
    public function addLoop($data): void
    {
        $length = is_array($data) || $data instanceof Countable ? count($data) : null;
        $parent = static::last($this->loopsStack);
        $this->loopsStack[] = [
            'index' => -1,
            'iteration' => 0,
            'remaining' => isset($length) ? $length + 1 : null,
            'count' => $length,
            'first' => true,
            'even' => true,
            'odd' => false,
            'last' => isset($length) ? $length == 1 : null,
            'depth' => count($this->loopsStack) + 1,
            'parent' => $parent ? (object)$parent : null,
        ];
    }

    /**
     * Increment the top loop's indices.
     *
     * @return object
     */
    public function incrementLoopIndices(): object
    {
        $c = count($this->loopsStack) - 1;
        $loop = &$this->loopsStack[$c];

        $loop['index']++;
        $loop['iteration']++;
        $loop['first'] = $loop['index'] == 0;
        $loop['even'] = $loop['index'] % 2 == 0;
        $loop['odd'] = !$loop['even'];
        if (isset($loop['count'])) {
            $loop['remaining']--;
            $loop['last'] = $loop['index'] == $loop['count'] - 1;
        }
        return (object)$loop;
    }

    /**
     * Pop a loop from the top of the loop stack.
     *
     * @return void
     */
    public function popLoop(): void
    {
        array_pop($this->loopsStack);
    }

    /**
     * Get an instance of the first loop in the stack.
     *
     * @return object
     */
    public function getFirstLoop(): ?object
    {
        return ($last = static::last($this->loopsStack)) ? (object)$last : null;
    }

    /**
     * Get the rendered contents of a partial from a loop.
     *
     * @param string $view
     * @param array  $data
     * @param string $iterator
     * @param string $empty
     * @return string
     * @throws Exception
     */
    public function renderEach($view, $data, $iterator, $empty = 'raw|'): string
    {
        $result = '';

        if (count($data) > 0) {
            // If is actually data in the array, we will loop through the data and append
            // an instance of the partial view to the final result HTML passing in the
            // iterated value of this data array, allowing the views to access them.
            foreach ($data as $key => $value) {
                $data = ['key' => $key, $iterator => $value];
                $result .= $this->runChild($view, $data);
            }
        } elseif (static::startsWith($empty, 'raw|')) {
            $result = substr($empty, 4);
        } else {
            $result = $this->run($empty, []);
        }
        return $result;
    }

    /**
     * Run the blade engine. It returns the result of the code.
     *
     * @param string|null $view      The name of the cache. Ex: "folder.folder.view" ("/folder/folder/view.blade")
     * @param array       $variables An associative arrays with the values to display.
     * @return string
     * @throws Exception
     */
    public function run($view = null, $variables = []): string
    {
        $mode = $this->getMode();

        if ($view === null) {
            $view = $this->viewStack;
        }
        $this->viewStack = null;
        if ($view === null) {
            $this->showError('run', 'View: view not set', true);
            return '';
        }

        $forced = $mode & 1; // mode=1 forced:it recompiles no matter if the compiled file exists or not.
        $runFast = $mode & 2; // mode=2 runfast: the code is not compiled neither checked, and it runs directly the compiled
        $this->sections = [];
        if ($mode == 3) {
            $this->showError('run', "we can't force and run fast at the same time", true);
        }
        return $this->runInternal($view, $variables, $forced, true, $runFast);
    }

    /**
     * It sets the current view<br>
     * This value is cleared when it is used (method run).<br>
     * <b>Example:<b><br>
     * <pre>
     * $this->setView('folder.view')->share(['var1'=>20])->run(); // or $this->run('folder.view',['var1'=>20]);
     * </pre>
     *
     * @param string $view
     * @return View
     */
    public function setView($view): View
    {
        $this->viewStack = $view;
        return $this;
    }

    /**
     * It injects a function, an instance, or a method class when a view is called.<br>
     * It could be stacked.   If it sets null then it clears all definitions.
     * <b>Example:<b><br>
     * <pre>
     * $this->composer('folder.view',function($view) { $view->share('newvalue', 'hi there'); });
     * $this->composer('folder.view', 'namespace1\namespace2\SomeClass'); // SomeClass must exist, and it must have the
     *                                                                   // method 'composer'
     * $this->composer('folder.*',$instance); // $instance must have the method called 'composer'
     * $this->composer(); // clear all composer.
     * </pre>
     *
     * @param string|array|null    $view It could contain wildcards (*). Example: 'aa.bb.cc', '*.bb.cc', 'aa.bb.*', '*.bb.*'
     *
     * @param callable|string|null $functionOrClass
     * @return View
     */
    public function composer($view = null, $functionOrClass = null): View
    {
        if ($view === null && $functionOrClass === null) {
            $this->composerStack = [];
            return $this;
        }
        if (is_array($view)) {
            foreach ($view as $v) {
                $this->composerStack[$v] = $functionOrClass;
            }
        } else {
            $this->composerStack[$view] = $functionOrClass;
        }

        return $this;
    }

    /**
     * Start a component rendering process.
     *
     * @param string $name
     * @param array  $data
     * @return void
     */
    public function startComponent($name, array $data = []): void
    {
        if (ob_start()) {
            $this->componentStack[] = $name;

            $this->componentData[$this->currentComponent()] = $data;

            $this->slots[$this->currentComponent()] = [];
        }
    }

    /**
     * Get the index for the current component.
     *
     * @return int
     */
    protected function currentComponent(): int
    {
        return count($this->componentStack) - 1;
    }

    /**
     * Render the current component.
     *
     * @return string
     * @throws Exception
     */
    public function renderComponent(): string
    {
        //echo "<hr>render<br>";
        $name = array_pop($this->componentStack);
        //return $this->runChild($name, $this->componentData());
        $cd = $this->componentData();
        $clean = array_keys($cd);
        $r = $this->runChild($name, $cd);
        // we clean variables defined inside the component (so they are garbaged when the component is used)
        foreach ($clean as $key) {
            unset($this->variables[$key]);
        }
        return $r;
    }

    /**
     * Get the data for the given component.
     *
     * @return array
     */
    protected function componentData(): array
    {
        $cs = count($this->componentStack);
        //echo "<hr>";
        //echo "<br>data:<br>";
        //var_dump($this->componentData);
        //echo "<br>datac:<br>";
        //var_dump(count($this->componentStack));
        return array_merge(
            $this->componentData[$cs],
            ['slot' => trim(ob_get_clean())],
            $this->slots[$cs]
        );
    }

    /**
     * Start the slot rendering process.
     *
     * @param string      $name
     * @param string|null $content
     * @return void
     */
    public function slot($name, $content = null): void
    {
        if (count(func_get_args()) === 2) {
            $this->slots[$this->currentComponent()][$name] = $content;
        } elseif (ob_start()) {
            $this->slots[$this->currentComponent()][$name] = '';

            $this->slotStack[$this->currentComponent()][] = $name;
        }
    }

    /**
     * Save the slot content for rendering.
     *
     * @return void
     */
    public function endSlot(): void
    {
        static::last($this->componentStack);

        $currentSlot = array_pop(
            $this->slotStack[$this->currentComponent()]
        );

        $this->slots[$this->currentComponent()][$currentSlot] = trim(ob_get_clean());
    }

    /**
     * @return string
     */
    public function getPhpTag(): string
    {
        return $this->phpTag;
    }

    /**
     * @param string $phpTag
     */
    public function setPhpTag($phpTag): void
    {
        $this->phpTag = $phpTag;
    }

    /**
     * @return string
     */
    public function getCurrentUser(): string
    {
        return $this->currentUser;
    }

    /**
     * @param string $currentUser
     */
    public function setCurrentUser($currentUser): void
    {
        $this->currentUser = $currentUser;
    }

    /**
     * @return string
     */
    public function getCurrentRole(): string
    {
        return $this->currentRole;
    }

    /**
     * @param string $currentRole
     */
    public function setCurrentRole($currentRole): void
    {
        $this->currentRole = $currentRole;
    }

    /**
     * @return string[]
     */
    public function getCurrentPermission(): array
    {
        return $this->currentPermission;
    }

    /**
     * @param string[] $currentPermission
     */
    public function setCurrentPermission(array $currentPermission): void
    {
        $this->currentPermission = $currentPermission;
    }

    /**
     * Returns the current base url without trailing slash.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * It sets the base url and, it also calculates the relative path.<br>
     * The base url defines the "root" of the project, not always the level of the domain, but it could be
     * any folder.<br>
     * This value is used to calculate the relativity of the resources, but it is also used to set the domain.<br>
     * <b>Note:</b> The trailing slash is removed automatically if it's present.<br>
     * <b>Note:</b> We should not use arguments or name of the script.<br>
     * <b>Examples:</b><br>
     * <pre>
     * $this->setBaseUrl('http://domain.dom/myblog');
     * $this->setBaseUrl('http://domain.dom/corporate/erp');
     * $this->setBaseUrl('http://domain.dom/blog.php?args=20'); // avoid this one.
     * $this->setBaseUrl('http://another.dom');
     * </pre>
     *
     * @param string $baseUrl Example http://www.web.com/folder  https://www.web.com/folder/anotherfolder
     * @return View
     */
    public function setBaseUrl($baseUrl): View
    {
        $this->baseUrl = rtrim($baseUrl, '/'); // base with the url trimmed
        $this->baseDomain = @parse_url($this->baseUrl)['host'];
        $currentUrl = $this->getCurrentUrlCalculated();
        if ($currentUrl === '') {
            $this->relativePath = '';
            return $this;
        }
        if (str_starts_with($currentUrl, $this->baseUrl)) {
            $part = str_replace($this->baseUrl, '', $currentUrl);
            $numf = substr_count($part, '/') - 1;
            $numf = ($numf > 10) ? 10 : $numf; // avoid overflow
            $this->relativePath = ($numf < 0) ? '' : str_repeat('../', $numf);
        } else {
            $this->relativePath = '';
        }
        return $this;
    }

    /**
     * It gets the full current url calculated with the information sends by the user.<br>
     * <b>Note:</b> If we set baseurl, then it always uses the baseurl as domain (it's safe).<br>
     * <b>Note:</b> This information could be forged/faked by the end-user.<br>
     * <b>Note:</b> It returns empty '' if it is called in a command line interface / non-web.<br>
     * <b>Note:</b> It doesn't return the user and password.<br>
     * @param bool $noArgs if true then it excludes the arguments.
     * @return string
     */
    public function getCurrentUrlCalculated($noArgs = false): string
    {
        if (!isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])) {
            return '';
        }
        $host = $this->baseDomain ?? $_SERVER['HTTP_HOST']; // <-- it could be forged!
        $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
        $port = $_SERVER['SERVER_PORT'];
        $port2 = (($link === 'http' && $port === '80') || ($link === 'https' && $port === '443')) ? '' : ':' . $port;
        $link .= "://$host$port2$_SERVER[REQUEST_URI]";
        if ($noArgs) {
            $link = @explode('?', $link)[0];
        }
        return $link;
    }

    /**
     * It returns the relative path to the base url or empty if not set<br>
     * <b>Example:</b><br>
     * <pre>
     * // current url='http://domain.dom/page/subpage/web.php?aaa=2
     * $this->setBaseUrl('http://domain.dom/');
     * $this->getRelativePath(); // '../../'
     * $this->setBaseUrl('http://domain.dom/');
     * $this->getRelativePath(); // '../../'
     * </pre>
     * <b>Note:</b>The relative path is calculated when we set the base url.
     *
     * @return string
     * @see \Sura\View\View::setBaseUrl
     */
    public function getRelativePath(): string
    {
        return $this->relativePath;
    }

    /**
     * It gets the full current canonical url.<br>
     * <b>Example:</b> https://www.mysite.com/aaa/bb/php.php?aa=bb
     * <ul>
     * <li>It returns the $this->canonicalUrl value if is not null</li>
     * <li>Otherwise, it returns the $this->currentUrl if not null</li>
     * <li>Otherwise, the url is calculated with the information sends by the user</li>
     * </ul>
     *
     * @return string|null
     */
    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl ?? $this->getCurrentUrl();
    }

    /**
     * It sets the full canonical url.<br>
     * <b>Example:</b> https://www.mysite.com/aaa/bb/php.php?aa=bb
     *
     * @param string|null $canonUrl
     * @return View
     */
    public function setCanonicalUrl($canonUrl = null): View
    {
        $this->canonicalUrl = $canonUrl;
        return $this;
    }

    /**
     * It gets the full current url<br>
     * <b>Example:</b> https://www.mysite.com/aaa/bb/php.php?aa=bb
     * <ul>
     * <li>It returns the $this->currentUrl if not null</li>
     * <li>Otherwise, the url is calculated with the information sends by the user</li>
     * </ul>
     *
     * @param bool $noArgs if true then it ignores the arguments.
     * @return string|null
     */
    public function getCurrentUrl($noArgs = false): ?string
    {
        $link = $this->currentUrl ?? $this->getCurrentUrlCalculated();
        if ($noArgs) {
            $link = @explode('?', $link)[0];
        }
        return $link;
    }

    /**
     * It sets the full current url.<br>
     * <b>Example:</b> https://www.mysite.com/aaa/bb/php.php?aa=bb
     * <b>Note:</b> If the current url is not set, then the system could calculate the current url.
     *
     * @param string|null $currentUrl
     * @return View
     */
    public function setCurrentUrl($currentUrl = null): View
    {
        $this->currentUrl = $currentUrl;
        return $this;
    }

    /**
     * If true then it optimizes the result (it removes tab and extra spaces).
     *
     * @param bool $bool
     * @return View
     */
    public function setOptimize($bool = false): View
    {
        $this->optimize = $bool;
        return $this;
    }

    /**
     * It sets the callback function for authentication. It is used by @can and @cannot
     *
     * @param callable $fn
     */
    public function setCanFunction(callable $fn): void
    {
        $this->authCallBack = $fn;
    }

    /**
     * It sets the callback function for authentication. It is used by @canany
     *
     * @param callable $fn
     */
    public function setAnyFunction(callable $fn): void
    {
        $this->authAnyCallBack = $fn;
    }

    /**
     * It sets the callback function for errors. It is used by @error
     *
     * @param callable $fn
     */
    public function setErrorFunction(callable $fn): void
    {
        $this->errorCallBack = $fn;
    }

    //</editor-fold>
    //<editor-fold desc="push">

    /**
     * Get the entire loop stack.
     *
     * @return array
     */
    public function getLoopStack(): array
    {
        return $this->loopsStack;
    }

    /**
     * It adds a string inside a quoted string<br>
     * <b>example:</b><br>
     * <pre>
     * $this->addInsideQuote("'hello'"," world"); // 'hello world'
     * $this->addInsideQuote("hello"," world"); // hello world
     * </pre>
     *
     * @param $quoted
     * @param $newFragment
     * @return string
     */
    public function addInsideQuote($quoted, $newFragment): string
    {
        if ($this->isQuoted($quoted)) {
            return substr($quoted, 0, -1) . $newFragment . substr($quoted, -1);
        }
        return $quoted . $newFragment;
    }

    /**
     * Return true if the string is a php variable (it starts with $)
     *
     * @param string|null $text
     * @return bool
     */
    public function isVariablePHP($text): bool
    {
        if (!$text || strlen($text) < 2) {
            return false;
        }
        return $text[0] === '$';
    }

    /**
     * It's the same as "@_e", however it parses the text (using sprintf).
     * If the operation fails then, it returns the original expression without translation.
     *
     * @param $phrase
     *
     * @return string
     */
    public function _ef($phrase): string
    {
        $argv = func_get_args();
        $r = $this->_e($phrase);
        $argv[0] = $r; // replace the first argument with the translation.
        $result = @sprintf(...$argv);
        return !$result ? $r : $result;
    }

    /**
     * Tries to translate the word if it's in the array defined by ViewLang::$dictionary
     * If the operation fails then, it returns the original expression without translation.
     *
     * @param $phrase
     *
     * @return string
     */
    public function _e($phrase): string
    {
        if ((!array_key_exists($phrase, static::$dictionary))) {
            $this->missingTranslation($phrase);
            return $phrase;
        }

        return static::$dictionary[$phrase];
    }

    /**
     * Log a missing translation into the file $this->missingLog.<br>
     * If the file is not defined, then it doesn't write the log.
     *
     * @param string $txt Message to write on.
     */
    protected function missingTranslation(mixed $txt): void
    {
        if (!$this->missingLog) {
            return; // if there is not a file assigned then it skips saving.
        }
        $fz = @filesize($this->missingLog);
        if (is_object($txt) || is_array($txt)) {
            $txt = print_r($txt, true);
        }
        // Rewrite file if more than 100000 bytes
        $mode = ($fz > 100000) ? 'w' : 'a';
        $fp = fopen($this->missingLog, $mode);
        fwrite($fp, $txt . "\n");
        fclose($fp);
    }

    /**
     * if num is more than one then it returns the phrase in plural, otherwise the phrase in singular.
     * Note: the translation should be as follows: $msg['Person']='Person' $msg=['Person']['p']='People'
     *
     * @param string $phrase
     * @param string $phrases
     * @param int    $num
     *
     * @return string
     */
    public function _n($phrase, $phrases, $num = 0): string
    {
        if ((!array_key_exists($phrase, static::$dictionary))) {
            $this->missingTranslation($phrase);
            return ($num <= 1) ? $phrase : $phrases;
        }

        return ($num <= 1) ? $this->_e($phrase) : $this->_e($phrases);
    }

    /**
     * @param $expression
     * @return string
     * @see \Sura\View\View::getCanonicalUrl
     */
    public function compileCanonical($expression = null): string
    {
        return '<link rel="canonical" href="' . $this->phpTag
            . ' echo $this->getCanonicalUrl();?>" />';
    }

    /**
     * @param $expression
     * @return string
     * @see \Sura\View\View::getBaseUrl()
     */
    public function compileBase($expression = null): string
    {
        return '<base rel="canonical" href="' . $this->phpTag
            . ' echo $this->getBaseUrl() ;?>" />';
    }

    protected function compileUse($expression): string
    {
        return $this->phpTag . 'use ' . $this->stripParentheses($expression) . '; ?>';
    }

    protected function compileSwitch($expression): string
    {
        $this->switchCount++;
        $this->firstCaseInSwitch = true;
        return $this->phpTag . "switch $expression {";
    }
    //</editor-fold>
    //<editor-fold desc="compile extras">

    protected function compileDump($expression): string
    {
        return $this->phpTagEcho . "\$this->dump$expression;?>";
    }

    protected function compileRelative($expression): string
    {
        return $this->phpTagEcho . "\$this->relative$expression;?>";
    }

    protected function compileMethod($expression): string
    {
        $v = $this->stripParentheses($expression);

        return "<input type='hidden' name='_method' value='{$this->phpTag}echo $v; " . "?>'/>";
    }

    /**
     * default tag used for switch/case
     *
     * @return string
     */
    protected function compileDefault(): string
    {
        if ($this->firstCaseInSwitch) {
            return $this->showError('@default', '@switch without any @case', true);
        }
        return $this->phpTag . 'default: ?>';
    }

    protected function compileEndSwitch(): string
    {
        --$this->switchCount;
        if ($this->switchCount < 0) {
            return $this->showError('@endswitch', 'Missing @switch', true);
        }
        return $this->phpTag . '} // end switch ?>';
    }

    /**
     * Remove first and end quote from a quoted string of text
     *
     * @param mixed $text
     * @return null|string|string[]
     */
    public function stripQuotes($text): array|string|null
    {
        if (!$text || strlen($text) < 2) {
            return $text;
        }
        $text = trim($text);
        $p0 = $text[0];
        $p1 = substr($text, -1);
        if ($p0 === $p1 && ($p0 === '"' || $p0 === "'")) {
            return substr($text, 1, -1);
        }
        return $text;
    }

    /**
     * Execute the user defined extensions.
     *
     * @param string $value
     * @return string
     */
    protected function compileExtensions($value): string
    {
        foreach ($this->extensions as $compiler) {
            $value = $compiler($value, $this);
        }
        return $value;
    }

    /**
     * Get the echo methods in the proper order for compilation.
     *
     * @return array
     */
    protected function getEchoMethods(): array
    {
        $methods = [
            'compileRawEchos' => strlen(stripcslashes($this->rawTags[0])),
            'compileEscapedEchos' => strlen(stripcslashes($this->escapedTags[0])),
            'compileRegularEchos' => strlen(stripcslashes($this->contentTags[0])),
        ];
        uksort($methods, static function ($method1, $method2) use ($methods) {
            // Ensure the longest tags are processed first
            if ($methods[$method1] > $methods[$method2]) {
                return -1;
            }
            if ($methods[$method1] < $methods[$method2]) {
                return 1;
            }
            // Otherwise, give preference to raw tags (assuming they've overridden)
            if ($method1 === 'compileRawEchos') {
                return -1;
            }
            if ($method2 === 'compileRawEchos') {
                return 1;
            }
            if ($method1 === 'compileEscapedEchos') {
                return -1;
            }
            if ($method2 === 'compileEscapedEchos') {
                return 1;
            }
            throw new BadMethodCallException("Method [$method1] not defined");
        });
        return $methods;
    }

    /**
     * Compile Blade statements that start with "@".
     *
     * @param string $value
     *
     * @return array|string|string[]|null
     */
    protected function compileStatements($value)
    {
        /**
         * @param array $match
         *                    [0]=full expression with @ and parenthesis
         *                    [1]=expression without @ and argument
         *                    [2]=????
         *                    [3]=argument with parenthesis and without the first @
         *                    [4]=argument without parenthesis.
         *
         * @return mixed|string
         */
        $callback = function ($match) {
            if (static::contains($match[1], '@')) {
                // @@escaped tag
                $match[0] = isset($match[3]) ? $match[1] . $match[3] : $match[1];
            } else {
                if (str_contains($match[1], '::')) {
                    // Someclass::method
                    return $this->compileStatementClass($match);
                }
                if (isset($this->customDirectivesRT[$match[1]])) {
                    if ($this->customDirectivesRT[$match[1]]) {
                        $match[0] = $this->compileStatementCustom($match);
                    } else {
                        $match[0] = call_user_func(
                            $this->customDirectives[$match[1]],
                            $this->stripParentheses(static::get($match, 3))
                        );
                    }
                } elseif (method_exists($this, $method = 'compile' . ucfirst($match[1]))) {
                    // it calls the function compile<name of the tag>
                    $match[0] = $this->$method(static::get($match, 3));
                } else {
                    return $match[0];
                }
            }
            return isset($match[3]) ? $match[0] : $match[0] . $match[2];
        };
        /* return \preg_replace_callback('/\B@(@?\w+)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x', $callback, $value); */
        return preg_replace_callback('/\B@(@?\w+(?:::\w+)?)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x', $callback, $value);
    }

    /**
     * Determine if a given string contains a given substring.
     *
     * @param string       $haystack
     * @param string|array $needles
     * @return bool
     */
    public static function contains($haystack, $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '') {
                if (function_exists('mb_strpos')) {
                    if (str_contains($haystack, $needle)) {
                        return true;
                    }
                } elseif (str_contains($haystack, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function compileStatementClass($match): string
    {
        if (isset($match[3])) {
            return $this->phpTagEcho . $this->fixNamespaceClass($match[1]) . $match[3] . '; ?>';
        }

        return $this->phpTagEcho . $this->fixNamespaceClass($match[1]) . '(); ?>';
    }

    /**
     * Util method to fix namespace of a class<br>
     * Example: "SomeClass::method()" -> "\namespace\SomeClass::method()"<br>
     *
     * @param string $text
     *
     * @return string
     * @see \Sura\View\View::$aliasClasses
     */
    protected function fixNamespaceClass(string $text): string
    {
        if (!str_contains($text, '::')) {
            return $text;
        }
        $classPart = explode('::', $text, 2);
        if (isset($this->aliasClasses[$classPart[0]])) {
            $classPart[0] = $this->aliasClasses[$classPart[0]];
        }
        return $classPart[0] . '::' . $classPart[1];
    }

    /**
     * For compile custom directive at runtime.
     *
     * @param $match
     * @return string
     */
    protected function compileStatementCustom($match): string
    {
        $v = $this->stripParentheses(static::get($match, 3));
        $v = ($v == '') ? '' : ', ' . $v;
        return $this->phpTag . 'call_user_func($this->customDirectives[\'' . $match[1] . '\']' . $v . '); ?>';
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param ArrayAccess|array $array
     * @param string            $key
     * @param mixed             $default
     * @return mixed
     */
    public static function get($array, $key, $default = null)
    {
        $accesible = is_array($array) || $array instanceof ArrayAccess;
        if (!$accesible) {
            return static::value($default);
        }
        if (is_null($key)) {
            return $array;
        }
        if (static::exists($array, $key)) {
            return $array[$key];
        }
        foreach (explode('.', $key) as $segment) {
            if (static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return static::value($default);
            }
        }
        return $array;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param ArrayAccess|array $array
     * @param int|string $key
     * @return bool
     */
    public static function exists(ArrayAccess|array $array, int|string $key): bool
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }
        return array_key_exists($key, $array);
    }

    /**
     * This method removes the parenthesis of the expression and parse the arguments.
     * @param string $expression
     * @return array
     */
    protected function getArgs(string $expression): array
    {
        return $this->parseArgs($this->stripParentheses($expression), ' ');
    }

    /**
     * It separates a string using a separator and a identifier<br>
     * It excludes quotes,double quotes and the "¬" symbol.<br>
     * <b>Example</b><br>
     * <pre>
     * $this->parseArgs('a=2,b='a,b,c',d'); // ['a'=>'2', 'b'=>'a,b,c', 'd'=>null]
     * $this->parseArgs('a=2,b=c,d'); // ['a'=>'2', 'b'=>'c', 'd'=>null]
     * $this->parseArgs('a=2 b=c', ' '); // ['a'=>'2', 'b'=>'c']
     * $this->parseArgs('a:2 b:c', ' ', ':'); // ['a'=>'2', 'b'=>'c']
     * </pre>
     * Note: parseArgs('a = 2 b = c', ' '); with return 4 values instead of 2.
     *
     * @param string $text      the text to separate
     * @param string $separator the separator of arguments
     * @param string $assigment the character used to assign a new value
     * @param bool   $emptyKey  if the argument is without value, we return it as key (true) or value (false) ?
     * @return array
     */
    public function parseArgs($text, $separator = ', ', $assigment = '=', $emptyKey = true): array
    {
        if ($text === null || $text === '') {
            return []; //nothing to convert.
        }
        $chars = $text; // str_split($text);
        $parts = [];
        $nextpart = '';
        $strL = strlen($chars);
        $stringArr = '"\'¬';
        $parenthesis = '([{';
        $parenthesisClose = ')]}';
        $insidePar = false;
        for ($i = 0; $i < $strL; $i++) {
            $char = $chars[$i];
            // we check if the character is a parenthesis.
            $pp = strpos($parenthesis, $char);
            if ($pp !== false) {
                // is a parenthesis, so we mark as inside a parenthesis.
                $insidePar = $parenthesisClose[$pp];
            }
            if ($char === $insidePar) {
                // we close the parenthesis.
                $insidePar = false;
            }
            if (str_contains($stringArr, $char)) { // if ($char === '"' || $char === "'" || $char === "¬") {
                // we found a string initializer
                $inext = strpos($text, $char, $i + 1);
                $inext = $inext === false ? $strL : $inext;
                $nextpart .= substr($text, $i, $inext - $i + 1);
                $i = $inext;
            } else {
                $nextpart .= $char;
            }
            if ($char === $separator && !$insidePar) {
                $parts[] = substr($nextpart, 0, -1);
                $nextpart = '';
            }
        }
        if ($nextpart !== '') {
            $parts[] = $nextpart;
        }
        $result = [];
        // duct taping for key= argument (it has a space). however, it doesn't work with key =argument
        /*
        foreach ($parts as $k=>$part) {
            if(substr($part,-1)===$assigment && isset($parts[$k+1])) {
                var_dump('ok');
                $parts[$k].=$parts[$k+1];
                unset($parts[$k+1]);
            }
        }
        */
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part) {
                $char = $part[0];
                if (str_contains($stringArr, $char)) { // if ($char === '"' || $char === "'" || $char === "¬") {
                    if ($emptyKey) {
                        $result[$part] = null;
                    } else {
                        $result[] = $part;
                    }
                } else {
                    $r = explode($assigment, $part, 2);
                    if (count($r) === 2) {
                        // key=value.
                        $result[trim($r[0])] = trim($r[1]);
                    } elseif ($emptyKey) {
                        $result[trim($r[0])] = null;
                    } else {
                        $result[] = trim($r[0]);
                    }
                }
            }
        }
        return $result;
    }

    public function parseArgsOld($text, $separator = ', '): array
    {
        if ($text === null || $text === '') {
            return []; //nothing to convert.
        }
        $chars = str_split($text);
        $parts = [];
        $nextpart = '';
        $strL = count($chars);
        /** @noinspection ForeachInvariantsInspection */
        for ($i = 0; $i < $strL; $i++) {
            $char = $chars[$i];
            if ($char === '"' || $char === "'") {
                $inext = strpos($text, $char, $i + 1);
                $inext = $inext === false ? $strL : $inext;
                $nextpart .= substr($text, $i, $inext - $i + 1);
                $i = $inext;
            } else {
                $nextpart .= $char;
            }
            if ($char === $separator) {
                $parts[] = substr($nextpart, 0, -1);
                $nextpart = '';
            }
        }
        if ($nextpart !== '') {
            $parts[] = $nextpart;
        }
        $result = [];
        foreach ($parts as $part) {
            $r = explode('=', $part, 2);
            $result[trim($r[0])] = count($r) === 2 ? trim($r[1]) : null;
        }
        return $result;
    }

    /**
     * It converts a string separated by pipes | into an filtered expression.<br>
     * If the method exists (as directive), then it is used<br>
     * If the method exists (in this class) then it is used<br>
     * Otherwise, it uses a global function.<br>
     * If you want to escape the "|", then you could use "/|"<br>
     * <b>Note:</b> It only works if $this->pipeEnable=true and by default it is false<br>
     * <b>Example:</b><br>
     * <pre>
     * $this->pipeDream('$name | strtolower | substr:0,4'); // strtolower(substr($name ,0,4)
     * $this->pipeDream('$name| getMode') // $this->getMode($name)
     * </pre>
     *
     * @param string $result
     * @return string
     * @\Sura\View\View::$pipeEnable
     */
    protected function pipeDream(string $result): string
    {
        $array = preg_split('~\\\\.(*SKIP)(*FAIL)|\|~s', $result);
        $c = count($array) - 1; // base zero.
        if ($c === 0) {
            return $result;
        }
        $prev = '';
        for ($i = 1; $i <=$c; $i++) {
            $r = @explode(':', $array[$i], 2);
            $fnName = trim($r[0]);
            $fnNameF = $fnName[0]; // first character
            if ($fnNameF === '"' || $fnNameF === '\'' || $fnNameF === '$' || is_numeric($fnNameF)) {
                $fnName = '!isset(' . $array[0] . ') ? ' . $fnName . ' : ';
            } elseif (isset($this->customDirectives[$fnName])) {
                $fnName = '$this->customDirectives[\'' . $fnName . '\']';
            } elseif (method_exists($this, $fnName)) {
                $fnName = '$this->' . $fnName;
            }
            $hasArgument=count($r) === 2;
            if ($i === 1) {
                $prev = $fnName . '(' . $array[0];
                if ($hasArgument) {
                    $prev .= ', ' . $r[1];
                }
                $prev .= ')';
            } else {
                $prev = $fnName . '(' . $prev;
                if ($hasArgument) {
                    $prev .=', '. $r[1] . ')';
                } else {
                    $prev.=')';
                }
            }
        }

        return $prev;
    }

    /**
     * Compile the "@each" tag into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileEach(string $expression): string
    {
        return $this->phpTagEcho . "\$this->renderEach$expression; ?>";
    }

    protected function compileSet($expression): string
    {
        //$segments = \explode('=', \preg_replace("/[()\\\']/", '', $expression));
        $segments = explode('=', $this->stripParentheses($expression));
        $value = (count($segments) >= 2) ? '=@' . implode('=', array_slice($segments, 1)) : '++';
        return $this->phpTag . trim($segments[0]) . $value . ';?>';
    }

    /**
     * Compile the yield statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileYield(string $expression): string
    {
        return $this->phpTagEcho . "\$this->yieldContent$expression; ?>";
    }

    /**
     * Compile the show statements into valid PHP.
     *
     * @return string
     */
    protected function compileShow(): string
    {
        return $this->phpTagEcho . '$this->yieldSection(); ?>';
    }

    /**
     * Compile the section statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileSection(string $expression): string
    {
        return $this->phpTag . "\$this->startSection$expression; ?>";
    }

    /**
     * Compile the append statements into valid PHP.
     *
     * @return string
     */
    protected function compileAppend(): string
    {
        return $this->phpTag . '$this->appendSection(); ?>';
    }

    /**
     * Compile the end-section statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndsection(): string
    {
        return $this->phpTag . '$this->stopSection(); ?>';
    }

    /**
     * Compile the stop statements into valid PHP.
     *
     * @return string
     */
    protected function compileStop(): string
    {
        return $this->phpTag . '$this->stopSection(); ?>';
    }

    /**
     * Compile the overwrite statements into valid PHP.
     *
     * @return string
     */
    protected function compileOverwrite(): string
    {
        return $this->phpTag . '$this->stopSection(true); ?>';
    }

    /**
     * Compile the unless statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileUnless(string $expression): string
    {
        return $this->phpTag . "if ( ! $expression): ?>";
    }

    /**
     * Compile the User statements into valid PHP.
     *
     * @return string
     */
    protected function compileUser(): string
    {
        return $this->phpTagEcho . "'" . $this->currentUser . "'; ?>";
    }

    /**
     * Compile the endunless statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndunless(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    /**
     * compile the {@}viewname statement<br>
     * {@}viewname('compiled') returns the full compiled path
     * {@}viewname('template') returns the full template path
     * {@}viewname('') returns the view name.
     *
     * @param mixed $expression
     *
     * @return string
     */
    protected function compileViewName(mixed $expression): string
    {
        $expression = $this->stripQuotes($this->stripParentheses($expression));
        return match ($expression) {
            'compiled' => $this->getCompiledFile($this->fileName),
            'template' => $this->getTemplateFile($this->fileName),
            default => $this->fileName,
        };
    }

    protected function compileAsset($expression): string
    {
        return $this->phpTagEcho . "(isset(\$this->assetDict[$expression]))?\$this->assetDict[$expression]:\$this->baseUrl.'/'.$expression; ?>";
    }

    //</editor-fold>

    /**
     * Used for @_e directive.
     *
     * @param $expression
     *
     * @return string
     */
    protected function compile_e($expression): string
    {
        return $this->phpTagEcho . "\$this->_e$expression; ?>";
    }

    /**
     * Used for @_ef directive.
     *
     * @param $expression
     *
     * @return string
     */
    protected function compile_ef($expression): string
    {
        return $this->phpTagEcho . "\$this->_ef$expression; ?>";
    }

    //</editor-fold>

    /**
     * Used for @_n directive.
     *
     * @param $expression
     *
     * @return string
     */
    protected function compile_n($expression): string
    {
        return $this->phpTagEcho . "\$this->_n$expression; ?>";
    }

    // </editor-fold>


    //<editor-fold desc="cli">
    public static function isCli(): bool
    {
        return !http_response_code();
    }

    /**
     * @param           $key
     * @param string $default  is the defalut value is the parameter is set
     *                            without value.
     * @param bool $set      it is the value returned when the argument is set but there is no value assigned
     * @return string
     */
    public static function getParameterCli($key, string $default = '', bool $set = true): bool|string
    {
        global $argv;
        $p = array_search('-' . $key, $argv, true);
        if ($p === false) {
            return $default;
        }
        if (isset($argv[$p + 1])) {
            return self::removeTrailSlash($argv[$p + 1]);
        }
        return $set;
    }

    protected static function removeTrailSlash($txt): string
    {
        return rtrim($txt, '/\\');
    }

    /**
     * @param string $str
     * @param string $type =['i', 'e', 's', 'w'][$i]
     * @return string
     */
    public static function colorLog(string $str, string $type = 'i'): string
    {
        return match ($type) {
            'e' => "\033[31m$str\033[0m",
            's' => "\033[32m$str\033[0m",
            'w' => "\033[33m$str\033[0m",
            'i' => "\033[36m$str\033[0m",
            'b' => "\e[01m$str\e[22m",
            default => $str,
        };
    }

    public function checkHealthPath(): bool
    {
        echo self::colorLog("Checking Health\n");
        $status=true;
        if (is_dir($this->compiledPath)) {
            echo "Compile-path [$this->compiledPath] is a folder " . self::colorLog("OK") . "\n";
        } else {
            $status=false;
            echo "Compile-path [$this->compiledPath] is not a folder " . self::colorLog("ERROR", 'e') . "\n";
        }
        foreach($this->templatePath as $t) {
            if (is_dir($t)) {
                echo "Template-path (view) [$t] is a folder " . self::colorLog("OK") . "\n";
            } else {
                $status = false;
                echo "Template-path (view) [$t] is not a folder " . self::colorLog("ERROR", 'e') . "\n";
            }
        }
        $error = self::colorLog('OK');
        try {
            /** @noinspection RandomApiMigrationInspection */
            $rnd = $this->compiledPath . '/dummy' . rand(10000, 900009);
            $f = @file_put_contents($rnd, 'dummy');
            if ($f === false) {
                $status=false;
                $error = self::colorLog("Unable to create file [" . $this->compiledPath . '/dummy]', 'e');
            }
            @unlink($rnd);
        } catch (Exception $ex) {
            $status=false;
            $error = self::colorLog($ex->getMessage(), 'e');
        }
        echo "Testing write in the compile folder [$rnd] $error\n";
        $files = @glob($this->templatePath[0] . '/*');
        echo "Testing reading in the view folder [" . $this->templatePath[0] . "].\n";
        echo "View(s) found :" . count($files) . "\n";
        return $status;
    }
    public function createFolders(): void
    {
        echo self::colorLog("Creating Folder\n");
        echo "Creating compile folder[" . self::colorLog($this->compiledPath, 'b') . "] ";
        if (!is_dir($this->compiledPath)) {
            $ok = mkdir($this->compiledPath, 0770, true);
            if ($ok === false) {
                echo self::colorLog("Error: Unable to create folder, check the permissions\n", 'e');
            } else {
                echo self::colorLog("OK\n");
            }
        } else {
            echo self::colorLog("Note: folder already exist.\n", 'w');
        }
        foreach($this->templatePath as $t) {
            echo "Creating template folder [" . self::colorLog($t, 'b') . "] ";
            if (!is_dir($t)) {
                $ok = mkdir($t, 0770, true);
                if ($ok === false) {
                    echo self::colorLog("Error: Unable to create folder, check the permissions\n", 'e');
                } else {
                    echo self::colorLog("OK\n");
                }
            } else {
                echo self::colorLog("Note: folder already exist.\n", 'w');
            }
        }
    }

    public function clearcompile(): int
    {
        echo self::colorLog("Clearing Compile Folder\n");
        $files = glob($this->compiledPath . '/*'); // get all file names
        $count = 0;
        foreach ($files as $file) { // iterate files
            if (is_file($file)) {
                $count++;
                echo "deleting [$file] ";
                $r = @unlink($file); // delete file
                if ($r) {
                    echo self::colorLog("OK\n");
                } else {
                    echo self::colorLog("ERROR\n", 'e');
                }
            }
        }
        echo "Files deleted $count\n";
        return $count;
    }

    public static function isAbsolutePath($path): bool
    {
        if (!$path) {
            return true;
        }
        if (DIRECTORY_SEPARATOR === '/') {
            // linux and macos
            return $path[0] === '/';
        }
        return $path[1] === ':';
    }
}
