<?php

namespace Sura\View\Compilers\Concerns;

trait CompilesHelpers
{
    protected function compilecsrf($value = null): string
    {
        $value = $value ?? "'_token'";
        return $this->compileHVal($this->phpTag, $value);
    }

    protected function compileHVal($val1, $val2)
    {
        $str_input = "<input type='hidden' name='$val1 echo $val2; ?>'";
        $str_value = "value='{$val1}echo \$this->csrf_token; " . "?>'/>";
        return $str_input . $str_value;
    }

    protected function compileDd($value): string
    {
        return $this->phpTagEcho . "'<pre>'; var_dump$value; echo '</pre>';?>";
    }
}
