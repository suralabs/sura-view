<?php

namespace Sura\View\Compilers\Concerns;

trait CompilesIncludes
{
    /**
     * Compile the include statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileInclude($expression): string
    {
        $expression = $this->stripParentheses($expression);
        return $this->phpTagEcho . '$this->runChild(' . $expression . '); ?>';
    }

    /**
     * It loads a compiled template and paste inside the code.<br>
     * It uses more disk space, but it decreases the number of includes<br>
     *
     * @param $expression
     * @return string
     * @throws Exception
     */
    protected function compileIncludeFast($expression): string
    {
        $expression = $this->stripParentheses($expression);
        $ex = $this->stripParentheses($expression);
        $exp = \explode(',', $ex);
        $file = $this->stripQuotes($exp[0] ?? null);
        $fileC = $this->getCompiledFile($file);
        if (!@\is_file($fileC)) {
            // if the file doesn't exist then it's created
            $this->compile($file, true);
        }
        return $this->getFile($fileC);
    }

    /**
     * Compile the include statements into valid PHP.
     *
     * @param string $value
     * @return string
     */
    protected function compileIncludeIf($value): string
    {
        return $this->phpTag . 'if ($this->templateExist' . $value . ') echo $this->runChild' . $value . '; ?>';
    }

    /**
     * Compile the include statements into valid PHP.
     *
     * @param string $value
     * @return string
     */
    protected function compileIncludeWhen($value): string
    {
        $expression = $this->stripParentheses($value);
        return $this->phpTagEcho . '$this->includeWhen(' . $value . '); ?>';
    }

    /**
     * Compile the includefirst statement
     *
     * @param string $exprevaluession
     * @return string
     */
    protected function compileIncludeFirst($value): string
    {
        $expression = $this->stripParentheses($value);
        return $this->phpTagEcho . '$this->includeFirst(' . $value . '); ?>';
    }
}
