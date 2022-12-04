<?php

namespace Sura\View\Compilers\Concerns;

trait CompilesHelpers
{
    protected function compilecsrf($expression = null): string
    {
        $expression = $expression ?? "'_token'";
        return "<input type='hidden' name='$this->phpTag echo $expression; ?>' value='{$this->phpTag}echo \$this->csrf_token; " . "?>'/>";
    }

    protected function compileDd($expression): string
    {
        return $this->phpTagEcho . "'<pre>'; var_dump$expression; echo '</pre>';?>";
    }
}