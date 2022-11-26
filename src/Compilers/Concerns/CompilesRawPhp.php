<?php


namespace Sura\View\Compilers\Concerns;


trait CompilesRawPhp
{
    /**
     * Compile the unset statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileUnset($expression): string
    {
        return $this->phpTag . "unset$expression; ?>";
    }

    /**
     * Compile the raw PHP statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compilePhp($expression): string
    {
        return $expression ? $this->phpTag . "$expression; ?>" : $this->phpTag;
    }

    //<editor-fold desc="setter and getters">

    /**
     * Compile end-php statement into valid PHP.
     *
     * @return string
     */
    protected function compileEndphp(): string
    {
        return ' ?>';
    }
}