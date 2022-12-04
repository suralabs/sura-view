<?php

namespace Sura\View\Compilers\Concerns;

trait CompilesStacks
{
    /**
     * Compile the stack statements into the content.
     *
     * @param string $expression
     * @return string
     */
    protected function compileStack($expression): string
    {
        return $this->phpTagEcho . "\$this->yieldPushContent$expression; ?>";
    }

    /**
     * Compile the endpush statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndpush(): string
    {
        return $this->phpTag . '$this->stopPush(); ?>';
    }

    /**
     * Compile the endpushonce statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndpushOnce(): string
    {
        return $this->phpTag . '$this->stopPush(); endif; ?>';
    }

    /**
     * Compile the push statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    public function compilePrepend($expression): string
    {
        return $this->phpTag . "\$this->startPush$expression; ?>";
    }
}