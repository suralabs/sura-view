<?php


namespace Sura\View\Compilers\Concerns;


trait CompilesConditionals
{
    /**
     * Compile the push statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    public function compilePush($expression): string
    {
        return $this->phpTag . "\$this->startPush$expression; ?>";
    }

    /**
     * Compile the push statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    public function compilePushOnce($expression): string
    {
        $key = '$__pushonce__' . \trim(\substr($expression, 2, -2));
        return $this->phpTag . "if(!isset($key)): $key=1;  \$this->startPush$expression; ?>";
    }

    /**
     * Compile the auth statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileAuth($expression = ''): string
    {
        $role = $this->stripParentheses($expression);
        if ($role == '') {
            return $this->phpTag . 'if(isset($this->currentUser)): ?>';
        }

        return $this->phpTag . "if(isset(\$this->currentUser) && \$this->currentRole==$role): ?>";
    }

    /**
     * Compile the elseauth statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileElseAuth($expression = ''): string
    {
        $role = $this->stripParentheses($expression);
        if ($role == '') {
            return $this->phpTag . 'else: ?>';
        }

        return $this->phpTag . "elseif(isset(\$this->currentUser) && \$this->currentRole==$role): ?>";
    }

    /**
     * Compile the end-auth statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndAuth(): string
    {
        return $this->phpTag . 'endif; ?>';
    }



    /**
     * Compile the guest statements into valid PHP.
     *
     * @param null $expression
     * @return string
     */
    protected function compileGuest($expression = null): string
    {
        if ($expression === null) {
            return $this->phpTag . 'if(!isset($this->currentUser)): ?>';
        }

        $role = $this->stripParentheses($expression);
        if ($role == '') {
            return $this->phpTag . 'if(!isset($this->currentUser)): ?>';
        }

        return $this->phpTag . "if(!isset(\$this->currentUser) || \$this->currentRole!=$role): ?>";
    }

    /**
     * Compile the else statements into valid PHP.
     *
     * @param $expression
     * @return string
     */
    protected function compileElseGuest($expression): string
    {
        $role = $this->stripParentheses($expression);
        if ($role == '') {
            return $this->phpTag . 'else: ?>';
        }

        return $this->phpTag . "elseif(!isset(\$this->currentUser) || \$this->currentRole!=$role): ?>";
    }

    /**
     * /**
     * Compile the end-auth statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndGuest(): string
    {
        return $this->phpTag . 'endif; ?>';
    }
}