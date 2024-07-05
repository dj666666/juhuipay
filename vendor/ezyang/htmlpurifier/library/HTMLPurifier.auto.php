<?php

namespace think;
class xssFilter{

    public function __construct()
    {
        /**
         * This is a stub include that automatically configures the include path.
         */

        set_include_path(dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
        require_once '../../vendor/HtmlPurifier/library/HTMLPurifier/Bootstrap.php';
        require_once '../../vendor/HtmlPurifier/library/HTMLPurifier.autoload.php';

        // vim: et sw=4 sts=4
    }
}
