#!/usr/bin/env php
<?php

$options = [
    'extension'     => '.tpl.php',
    'new_extension' => '.tpl.twig',
    'recursive'     => false,
];

// First we need to get some arguments
if ($argc > 1) {
    $files = [];
    $current_dir = getcwd();

    // Look for commonly found arguments
    $cargv = count($argv);
    for ($i = 1; $i <= $argc && $i <= $cargv - 1; $i++) {
        switch ($argv[$i]) {
            case '-h':
            case '--help':
                print <<<HELP
php2twig: Convert PHPTemplate files to twig
Usage:
  Provide a location to a file or directory as argument to convert all .tpl.php
  files.
Options:
  -h    Display this help
  -r    Recursive
  -e    Extension (default: .tpl.php)
  -ne   New extension (default: .tpl.twig)
  -v    Verbose
Examples:
  - ./php2twig -ne .html.twig **/*.tpl.php
  - ./php2twig -r 1 .
HELP;
                break;

                // Extension
            case '-e':
                if (!isset($argv[$i + 1])) {
                    die("You must provide an extension");
                }
                $options['extension'] = $argv[$i + 1];
                $i++;
                break;

                // New extension
            case '-ne':
                if (!isset($argv[$i + 1])) {
                    die("You must provide an extension");
                }
                $options['new_extension'] = $argv[$i + 1];
                $i++;
                break;

                // Recursive
            case '-r':
                $options['recursive'] = true;
                break;

                // Files
            default:
                if (!isset($argv[$i])) {
                    break 2;
                }

                // Treat locations
                $file = $argv[$i];
                if (is_file($file)) {
                    $files[] = $file;
                } else {
                    echo "$file is not a valid file path.\n";
                }
        }
    }

    // Treat files
    if (!empty($files)) {
        foreach ($files as $file) {
            $new_name = str_replace($options['extension'], $options['new_extension'], basename($file));
            echo "Converting $file to $new_name\n";
            $converted = convert_phptemplate(file_get_contents($file));
            file_put_contents(dirname($file).'/'.$new_name, $converted, FILE_TEXT);
        }
    }
} else {
    $str = file_get_contents('php://stdin');
    if ($str) {
        print convert_phptemplate($str);
    } else {
        die("No file or directory specified");
    }
}

/**
 * Function
 *
 * @param $str string
 * @return array
 */
function convert_phptemplate($str)
{
    $tokens = token_get_all($str);
    $output = '';

    // Unsupported tokens
    $mapping = [
        T_ARRAY, // array()
        T_BREAK, // break",
        T_CASE, // , // case in a switch",
        T_CONTINUE, // ", // continue in a loop",
        T_CURLY_OPEN, // ", // complex variable parsed syntax",
        T_DEFAULT, // ", // default in a switch",
        T_DO, // ", // do in a do..while",
        T_DOLLAR_OPEN_CURLY_BRACES, // complex variable parsed syntax",
        T_ENCAPSED_AND_WHITESPACE, // \" \$a\" constant part of string with variables",
        T_ENDSWITCH, // endswitch switch, alternative syntax",
        T_ENDWHILE, // endwhile while, alternative syntax",
        T_FOR, // for",
        T_INCLUDE, // include()",
        T_INCLUDE_ONCE, // include_once()",
        T_NUM_STRING, // \"\$a[0]\" numeric array index inside string",
        T_REQUIRE, // require()",
        T_REQUIRE_ONCE, // require_once()",
        T_STRING_VARNAME, // \"$\{a complex variable parsed syntax",
        T_SWITCH, // switch",
    ];

    // LIFO list of end tags
    $nesting = [];
    // list of flags
    $flags = [];

    $debug = 0;

    while ($token = current($tokens)) {
        $index = key($tokens);

        // Display information about tokens
        if (is_array($token) && ($debug || in_array($token[0], $mapping))) {
            echo "Index $index, Line {$token[2]}: ", token_name($token[0]), "\t\t{$token[1]}", PHP_EOL;
        } elseif ($debug) {
            echo "Index $index, $token", PHP_EOL;
        }

        if (is_array($token)) {
            // Token, not characters
            $name = $token[0];
            $value = $token[1];

            // Some tokens depends on following expressions (+=, ++, etc.)
            if (isset($tokens[$index + 1])) {
                $i = $index;
                while (isset($tokens[$i + 1]) && $tokens[$i + 1][0] == T_WHITESPACE) {
                    $i++;
                }
                $next = $tokens[$i + 1];
                if (is_array($next)) {
                    if (in_array($next[0], [T_OR_EQUAL, T_PLUS_EQUAL, T_MUL_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL])) {
                        $output .= $value.' '.substr($token[1], 0, 1).' '.$value;
                        next($tokens);
                        continue;
                    } elseif ($next[0] == T_INC) {
                        $output .= "{% $value = $value + 1 %}";
                        next($tokens);
                        next($tokens);
                        continue;
                    } elseif ($next[0] == T_DEC) {
                        $output .= "{% $value = $value - 1 %}";
                        next($tokens);
                        next($tokens);
                        continue;
                    }
                }
            }

            if (in_array($name, [
                T_OR_EQUAL,
                T_PLUS_EQUAL,
                T_MUL_EQUAL,
                T_MINUS_EQUAL,
                T_ISSET,
                T_DOUBLE_ARROW,
                T_OPEN_TAG,
                T_DOC_COMMENT, // TODO Convert to twig doc?
            ])) {
                // Ignore these tokens
            } elseif (in_array($name, [T_CLOSE_TAG, T_INLINE_HTML])) {
                // When closing tags or displaying HTML, end nesting
                $nest = array_pop($nesting);
                // Remove last space for filters
                if (isset($nest[0]) && $nest[0] == '|') {
                    $output = rtrim($output, ' ');
                }
                $output .= $nest;
                $output .= str_replace("?>", "", $value);
            } elseif (in_array($name, [
                T_WHITESPACE,
                T_LOGICAL_AND,
                T_LOGICAL_OR,
                T_CONSTANT_ENCAPSED_STRING,
                T_LNUMBER,
                T_DNUMBER,
                T_IS_GREATER_OR_EQUAL,
                T_IS_SMALLER_OR_EQUAL,
            ])) {
                // These are the same in twig
                $output .= $value;
            } elseif ($name == T_VARIABLE) {
                // Variable, remove leading '$'
                $output .= substr($value, 1);
            } elseif ($name == T_ECHO || $name == T_PRINT) {
                // Print something
                $output .= '{{';
                $nesting[] = '}}';
            } elseif ($name == T_STRING) {
                // Function call
                if ($value == 't') {
                    $nest = array_pop($nesting);
                    $nesting[] = '|t '.$nest;
                } elseif ($value == 'render') {
                    // Ignore
                } elseif (in_array('inside_object_method', $flags) && next($tokens) !== '(') {
                    $output .= $value;
                } else {
                    $output .= $value.'(';
                    $nesting[] = ')';
                    $flags[] = 'inside_func_call';
                    echo "Unsupported function $value().\n";
                }
            // TODO maybe handle theme() ?
            } elseif ($name == T_IS_EQUAL || $name == T_IS_IDENTICAL) {
                $output .= ' is ';
            } elseif ($name == T_IS_NOT_EQUAL || $name == T_IS_NOT_IDENTICAL) {
                $output .= ' is not ';
            } elseif ($name == T_BOOLEAN_AND) {
                $output .= ' and ';
            } elseif ($name == T_BOOLEAN_OR) {
                $output .= ' or ';
            } elseif ($name == T_IF) {
                // There should be an expression after this, so we close it in ':' or '{'
                $output .= '{% if';
                $nesting[] = '%}';
                $flags[] = 'inside_condition';
                $flags[] = 'inside_if';
            } elseif ($name == T_ENDIF) {
                if (in_array('inside_if', $flags)) {
                    unset($flags[array_search('inside_if', $flags)]);
                }
                $output .= '{% endif %}';
            } elseif ($name == T_ENDFOR || $name == T_ENDFOREACH) {
                $output .= '{% endfor %}';
            } elseif ($name == T_ELSEIF) {
                // There should be an expression after this, so we close it in ':' or '{'
                $output .= '{% elseif ';
                $flags[] = 'inside_condition';
                $nesting[] = '%}';
            } elseif ($name == T_ELSE) {
                $output .= '{% else %} ';
            } elseif ($name == T_OBJECT_OPERATOR) {
                // This is object call or property ->
                $flags[] = 'inside_object_method';
                $output .= '.';
            } elseif ($name == T_EMPTY) {
                // !empty() is managed in '!', flag to be closed in '?'
                $flags[] = 'inside_empty_condition';
                $nesting[] = 'is empty ';
            } elseif ($name == T_COMMENT) {
                if ($value[1] == '*') {
                    // Multiline comment, remove ' * ' on each line
                    $value = preg_replace('@/\*(.+)\s+\*/@sU', '{# $1 #}', $value);
                    $value = preg_replace('@\s{3}\*\s@s', '', $value);
                    $output .= $value;
                } else {
                    // Single line comment that may be multiline (lol), so take care of this
                    $output .= "{# ";
                    prev($tokens);
                    $matches = null;
                    while (($token = next($tokens)) && is_array($token) && in_array(
                        $token[0],
                        [T_WHITESPACE, T_COMMENT]
                    )) {
                        if ($token[0] == T_COMMENT) {
                            if (isset($matches[2])) {
                                $output .= "$matches[2]";
                            }
                            preg_match('@^//\s?(.*)(\s*)@', $token[1], $matches);
                            $output .= "$matches[1]";
                        } else {
                            $matches[2] .= "$token[1]";
                        }
                    }
                    $output .= " #}".$matches[2];
                    prev($tokens);
                }
            } elseif ($name == T_FOREACH) {
                // foreach($iterated as $index => $value) -> for(value in iterated) _or_ for(index, value in iterated)
                $output .= '{% for';
                while (!is_array($token) || !in_array($token[0], [T_VARIABLE, T_STRING])) {
                    $token = next($tokens);
                }
                $iterated = substr($token[1], 1);
                while (!is_array($token) || $token[0] !== T_AS) {
                    $token = next($tokens);
                }
                while (!is_array($token) || $token[0] !== T_VARIABLE) {
                    $token = next($tokens);
                }
                $index_name = substr($token[1], 1);

                // Try to find value in next four tokens
                $i = 6;
                while (($token = next($tokens)) && $i) {
                    if (is_array($token) && $token[0] == T_VARIABLE) {
                        $value = substr($token[1], 1);
                        $output .= " $index_name, $value in $iterated %}";
                        break;
                    }
                    $i--;
                }
                if (!$i) {
                    $output .= " $index_name in $iterated %}";
                    // We've got too far, go back a bit
                    prev($tokens);
                    prev($tokens);
                    prev($tokens);
                    prev($tokens);
                }
            } else {
                // unsupported
                echo "WARNING: UNSUPPORTED ".token_name($token[0])."\n";
            }
        } else {
            // Characters
            switch ($token) {
                case ';':
                    // This close a function call or a print
                    if (in_array('inside_func_call', $flags)) {
                        $output .= array_pop($nesting);
                        unset($flags[array_search('inside_func_call', $flags)]);
                    }
                    if (in_array('}}', $nesting)) {
                        $output .= ' '.array_pop($nesting);
                    }
                    break;

                case ':':
                    // This must print inside ternary, else we don't care
                    if (in_array('inside_ternary', $flags)) {
                        $output .= $token;
                        unset($flags[array_search('inside_ternary', $flags)]);
                    }
                    // This might close a if expression
                    if (in_array('inside_condition', $flags)) {
                        $output .= ' '.array_pop($nesting);
                        unset($flags[array_search('inside_condition', $flags)]);
                    }
                    break;

                case '(':
                case ')':
                case ']':
                    // Ignored
                    break;

                case '{':
                    // This might close an expression
                    if (count($nesting)) {
                        $output .= array_pop($nesting);
                    }
                    break;

                case '}':
                    // This might close a if block
                    if (in_array('inside_if', $flags)) {
                        $output .= '{% endif %}';
                        unset($flags[array_search('inside_if', $flags)]);
                    }
                    break;

                case '[':
                    // Accessing key of an array
                    $next = next($tokens);
                    if (is_array($next) && $next[0] == T_CONSTANT_ENCAPSED_STRING) {
                        if ($next[1][1] == '#') {
                            // verify unsupported first characters
                            $output .= "[$next[1]]";
                        } else {
                            // else remove quotes
                            $output .= '.'.substr($next[1], 1, -1);
                        }
                    } elseif (is_array($next) && $next[0] == T_VARIABLE) {
                        // Remove leading '$'
                        $output .= '.'.substr($next[1], 1);
                    } elseif (is_array($next) && $next[0] == T_LNUMBER) {
                        // no treatment on numbers
                        $output .= '.'.$next[1];
                    } elseif (is_array($next) && $next[0] == T_WHITESPACE) {
                        $output .= ' '.$next[1];
                    } elseif (is_array($next)) {
                        die("Unsupported array/object traversing with ".token_name($next[0]));
                    } elseif (is_string($next)) {
                        if ($next == ']') {
                            $output .= '=>'.$next;
                        } elseif ($next == '[') {
                            $output .= '=>'.$next;
                        } else {
                            die("Unsupported array/object traversing with $next");
                        }
                    } else {
                        die("Unsupported array/object traversing with $next");
                    }
                    break;

                case '!':
                    // Verify next token is not empty()
                    $next = next($tokens);
                    if (is_array($next) && $next[0] == T_EMPTY) {
                        // if empty(), ignore
                    } else {
                        prev($tokens);
                        $output .= "not ";
                    }
                    break;

                case '.':
                    // Concatenation
                    $output .= '~';
                    break;

                case '?':
                    // Ternary condition
                    if (in_array('inside_empty_condition', $flags)) {
                        $output .= array_pop($nesting);
                        unset($flags[array_search('inside_empty_condition', $flags)]);
                    }
                    $flags[] = 'inside_ternary';
                    $output .= $token;
                    break;

                case ',':
                case '%':
                case '*':
                case '/':
                case '+':
                case '-':
                    // Same as twig
                    $output .= $token;
                    break;
            }
        }
        next($tokens);
    }

    if ($debug) {
        echo $str, $output, "\n";
    }

    return $output;
}
