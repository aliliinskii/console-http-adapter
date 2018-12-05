<?php
namespace ConsoleHttpAdapter;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArgvInput;

class InputBuilder
{
    /**
     * Create input from query
     * @tutorial
     * <pre>
     * Arguments are meant to be recieved via URL segments.
     * Query string is used to fill command options.
     * Query string is recieved either from $_SERVER['QUERY_STRING] or Psr\Http\Message\UriInterface::getQuery().
     * To pass a boolean option, value should be ommitted.
     * </pre>
     * @param array $args
     * @param string $query
     * @return InputInterface
     * @example
     * /entry-point/command-name/arg1/arg2?--no-value&--value=1
     * resolves to arguments [command-name, arg1, arg2] and options [--no-value => true, --value => 1]
     * @param array $args
     * @param string $query
     * @param array $default_options
     * @return InputInterface
     */
    public function createInput(array $args, string $query, array $default_options = []): InputInterface
    {
        $tokens = array_merge([null], $default_options, array_filter(explode('&', $query)), $args);

        return new ArgvInput(array_map('urldecode', $tokens));
    }
}
