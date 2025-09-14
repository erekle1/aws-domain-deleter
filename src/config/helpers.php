<?php

if (!function_exists('dd')) {
    /**
     * Dump a variable and terminate the script.
     *
     * @param mixed ...$vars
     * @return void
     */
    function dd(...$vars)
    {
        foreach ($vars as $var) {
            \Symfony\Component\VarDumper\VarDumper::dump($var);
        }
        exit(1);
    }
}