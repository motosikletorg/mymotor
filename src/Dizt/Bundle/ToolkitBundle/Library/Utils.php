<?php
namespace Dizt\Bundle\ToolkitBundle\Library;

/**
 * Created by PhpStorm.
 * User: deniz
 * Date: 31/08/15
 * Time: 01:32
 */
class Utils
{

    /**
     * returns true if $mixed is json compatible , otherwise returns false
     * @param $mixed
     */
    public static function isJsonString($mixed)
    {
        json_decode($mixed);
        return (json_last_error() == JSON_ERROR_NONE);
    }

}