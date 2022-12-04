<?php

namespace Sura\View\Compilers\Concerns;

trait CompilesInjections
{
    /**
     * Resolve a given class using the injectResolver callable.
     *
     * @param string      $className
     * @param string|null $variableName
     * @return mixed
     */
    protected function injectClass($className, $variableName = null)
    {
        if (isset($this->injectResolver)) {
            return call_user_func($this->injectResolver, $className, $variableName);
        }

        $fullClassName = $className . "\\" . $variableName;
        return new $fullClassName();
    }

    /**
     * Compile while statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileInject($expression): string
    {
        $ex = $this->stripParentheses($expression);
        $p0 = \strpos($ex, ',');
        if (!$p0) {
            $var = $this->stripQuotes($ex);
            $namespace = '';
        } else {
            $var = $this->stripQuotes(\substr($ex, 0, $p0));
            $namespace = $this->stripQuotes(\substr($ex, $p0 + 1));
        }
        return $this->phpTag . "\$$var = \$this->injectClass('$namespace', '$var'); ?>";
    }
}
