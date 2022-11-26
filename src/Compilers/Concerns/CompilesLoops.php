<?php


namespace Sura\View\Compilers\Concerns;


trait CompilesLoops
{
    /**
     * Compile the else statements into valid PHP.
     *
     * @return string
     */
    protected function compileElse(): string
    {
        return $this->phpTag . 'else: ?>';
    }

    /**
     * Compile the for statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileFor($expression): string
    {
        return $this->phpTag . "for$expression: ?>";
    }
    //</editor-fold>
    //<editor-fold desc="string functions">

    /**
     * Compile the foreach statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileForeach($expression): string
    {
        //\preg_match('/\( *(.*) * as *([^\)]*)/', $expression, $matches);
        if($expression===null) {
            return '@foreach';
        }
        \preg_match('/\( *(.*) * as *([^)]*)/', $expression, $matches);
        $iteratee = \trim($matches[1]);
        $iteration = \trim($matches[2]);
        $initLoop = "\$__currentLoopData = $iteratee; \$this->addLoop(\$__currentLoopData);\$this->getFirstLoop();\n";
        $iterateLoop = '$loop = $this->incrementLoopIndices(); ';
        return $this->phpTag . "$initLoop foreach(\$__currentLoopData as $iteration): $iterateLoop ?>";
    }

    /**
     * Compile a split of a foreach cycle. Used for example when we want to separate limites each "n" elements.
     *
     * @param string $expression
     * @return string
     */
    protected function compileSplitForeach($expression): string
    {
        return $this->phpTagEcho . '$this::splitForeach' . $expression . '; ?>';
    }

    /**
     * Compile the break statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileBreak($expression): string
    {
        return $expression ? $this->phpTag . "if$expression break; ?>" : $this->phpTag . 'break; ?>';
    }

    /**
     * Compile the continue statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileContinue($expression): string
    {
        return $expression ? $this->phpTag . "if$expression continue; ?>" : $this->phpTag . 'continue; ?>';
    }

    /**
     * Compile the forelse statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileForelse($expression): string
    {
        $empty = '$__empty_' . ++$this->forelseCounter;
        return $this->phpTag . "$empty = true; foreach$expression: $empty = false; ?>";
    }

    /**
     * Compile the if statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileIf($expression): string
    {
        return $this->phpTag . "if$expression: ?>";
    }
    //</editor-fold>
    //<editor-fold desc="loop functions">

    /**
     * Compile the else-if statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileElseif($expression): string
    {
        return $this->phpTag . "elseif$expression: ?>";
    }

    /**
     * Compile the forelse statements into valid PHP.
     *
     * @param string $expression empty if it's inside a for loop.
     * @return string
     */
    protected function compileEmpty($expression = ''): string
    {
        if ($expression == '') {
            $empty = '$__empty_' . $this->forelseCounter--;
            return $this->phpTag . "endforeach; if ($empty): ?>";
        }
        return $this->phpTag . "if (empty$expression): ?>";
    }

    /**
     * Compile the has section statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileHasSection($expression): string
    {
        return $this->phpTag . "if (! empty(trim(\$this->yieldContent$expression))): ?>";
    }

    /**
     * Compile the end-while statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndwhile(): string
    {
        return $this->phpTag . 'endwhile; ?>';
    }

    /**
     * Compile the end-for statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndfor(): string
    {
        return $this->phpTag . 'endfor; ?>';
    }

    /**
     * Compile the end-for-each statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndforeach(): string
    {
        return $this->phpTag . 'endforeach; $this->popLoop(); $loop = $this->getFirstLoop(); ?>';
    }


    /**
     * Compile the end-can statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndcan(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    /**
     * Compile the end-can statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndcanany(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    /**
     * Compile the end-cannot statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndcannot(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    /**
     * Compile the end-if statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndif(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    /**
     * Compile the end-for-else statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndforelse(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    /**
     * Execute the case tag.
     *
     * @param $expression
     * @return string
     */
    protected function compileCase($expression): string
    {
        if ($this->firstCaseInSwitch) {
            $this->firstCaseInSwitch = false;
            return 'case ' . $expression . ': ?>';
        }
        return $this->phpTag . "case $expression: ?>";
    }

    /**
     * Compile the while statements into valid PHP.
     *
     * @param string $expression
     * @return string
     */
    protected function compileWhile($expression): string
    {
        return $this->phpTag . "while$expression: ?>";
    }

}