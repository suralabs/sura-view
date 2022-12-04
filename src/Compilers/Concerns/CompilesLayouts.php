<?php

declare(strict_types=1);

namespace Sura\View\Compilers\Concerns;

/**
 * Trait CompilesLayouts
 * @package Sura\View\Compilers\Concerns
 */
trait CompilesLayouts
{
    /**
     * Compile the extends statements into valid PHP.
     *
     * @param string $value
     * @return string
     */
    protected function compileExtends($value): string
    {
        $value = $this->stripParentheses($value);
        // $_shouldextend avoids to runchild if it's not evaluated.
        // For example @if(something) @extends('aaa.bb') @endif()
        // If something is false then it's not rendered at the end (footer) of the script.
        $this->uidCounter++;

        $data = $this->compileExtStr($this->phpTag, $this->uidCounter, $value);
        $this->footer[] = $data;
        return $this->phpTag . '$_shouldextend[' . $this->uidCounter . ']=1; ?>';
    }

    protected function compileExtStr($val1, $val2, $val3)
    {
        return $val1 . 'if (isset($_shouldextend[' . $val2 . '])) { echo $this->runChild(' . $val3 . '); } ?>';
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
