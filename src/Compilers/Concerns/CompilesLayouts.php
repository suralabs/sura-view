<?php
declare(strict_types=1);

namespace Sura\View\Compilers\Concerns;

/**
 * Trait CompilesLayouts
 * @package Sura\View\Compilers\Concerns
 */
trait  CompilesLayouts
{
    /**
     * Compile the extends statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileExtends($expression): string
    {
        $expression = $this->stripParentheses($expression);
        // $_shouldextend avoids to runchild if it's not evaluated.
        // For example @if(something) @extends('aaa.bb') @endif()
        // If something is false then it's not rendered at the end (footer) of the script.
        $this->uidCounter++;
        $data = $this->phpTag . 'if (isset($_shouldextend[' . $this->uidCounter . '])) { echo $this->runChild(' . $expression . '); } ?>';
        $this->footer[] = $data;
        return $this->phpTag . '$_shouldextend[' . $this->uidCounter . ']=1; ?>';
    }

    /**
     * Execute the @parent command. This operation works in tandem with extendSection
     *
     * @return string
     * @see extendSection
     */
    protected function compileParent(): string
    {
        return $this->PARENTKEY;
    }
}