<?php

namespace Sura\View\Compilers\Concerns;

trait CompilesHelpers
{
    protected function compilecsrf($value = null): string
    {
        $value = $value ?? "'_token'";
        return "<input type='hidden' name='$this->phpTag echo $value; ?>' value='{$this->phpTag}echo \$this->csrf_token; " . "?>'/>";
    }

    protected function compileDd($value): string
    {
        return $this->phpTagEcho . "'<pre>'; var_dump$value; echo '</pre>';?>";
    }
}
