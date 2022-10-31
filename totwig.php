#!/usr/bin/env php
<?php

function findPreviousZendToken($currentIndex, $tokens, $searchThisTokens = [])
{
    if (!$tokens) {
        return ;
    }

    if (!(count($searchThisTokens) > 0)) {
        return;
    }

    if (!($currentIndex > 0)) {
        return;
    }

    $i = $currentIndex;
    while (isset($tokens[$i - 1])) {
        if (in_array($tokens[$i - 1][0], $searchThisTokens)) {
            return [
              'next' => $tokens[$i - 1],
              'index' => $i
            ];
        }
        $i--;
    }

    return null;
}
function findNextZendToken($currentIndex, $tokens, $searchThisTokens = [ T_WHITESPACE ], $mode = 'token')
{
    if (!$tokens) {
        return ;
    }

    if (!(count($searchThisTokens) > 0)) {
        return;
    }

    if (!($currentIndex > 0)) {
        return;
    }

    $i = $currentIndex;
    while (isset($tokens[$i + 1])) {
        if ($mode === 'token' && in_array($tokens[$i + 1][0], $searchThisTokens)) {
            return [
              'next' => $tokens[$i + 1],
              'index' => $i
            ];
        } else {
            if (in_array($tokens[$i + 1][0], $searchThisTokens)) {
                return [
                        'next' => $tokens[$i + 1],
                        'index' => $i
                      ];
            }
        }
        $i++;
    }

    return null;
}


class TwigConverter
{

  private $options = [
    'extension' => '.tpl.php',
    'new_extension' => '.tpl.twig',
    'recursive' => false,
  ];

  // LIFO list of end tags
    private $nesting = [];
    // list of flags
   private  $flags = [];

   private  $debug = 0;

  // Unsupported tokens
    private $mapping = [
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

  function convertFile($fileName)
  {
    $new_name = str_replace($this->options['extension'], $this->options['new_extension'], basename($fileName));
    echo "Converting $fileName to $new_name\n";
    $converted = $this->convertPhpTemplate(file_get_contents($fileName));
    file_put_contents(dirname($fileName) . '/' . $new_name, $converted, FILE_TEXT);
  }

  private $tokens = [];
  private $output = '';

  function convertPhpTemplate($contentString) {
    $this->tokens = token_get_all($contentString);
    $this->output = '';
    $this->nesting = [];
    $this->flags = [];
    $this->processTokens();

    return $this->output;
  }

  // Check Token is not character
  function checkIsPHPToken($token) {
      return is_array($token);
  }

 // Check Token is character
  function checkIsStringToken($token) {
      return is_string($token);
  }

  // Check token is defined
  function checkToken($token){
    return isset($token);
  }

  // Get first php token except space
  function getFirstPHPToken($index){
    $i = $index;
    while (isset($this->tokens[$i + 1]) && $this->tokens[$i + 1][0] == T_WHITESPACE) {
        $i++;
    }
    return [ 'token' => $this->tokens[$i + 1], 'index' => $i ];
  }


  public function processTokens(){
    while($token = current($this->tokens)){
      $currentTokenIndex = key($this->tokens);
      $token[3] = $currentTokenIndex;

      if($this->checkIsPHPToken($token)){
        // Token, not characters
        $name = $token[0];
        $value = $token[1];


      if(isset($this->tokens[$currentTokenIndex + 1]) && $this->checkToken($this->tokens[$currentTokenIndex + 1])){
          $next = $this->getFirstPHPToken($currentTokenIndex);
          if(isset($next['token']) && $this->checkToken($next['token']) && $this->checkIsPHPToken($next['token'])){
            if($this->checkAndProcessMathematicalOperator($token, $next['token'], $next['index'])){
              continue;
            }else if($this->checkAndProcessVariableIncremention($token, $next['token'], $next['index'])){
              continue;
            }else if($this->checkAndProcessVariableDecrease($token, $next['token'], $next['index'])){
              continue;
            }
          }
        }

        if (in_array($name, [ T_OR_EQUAL, T_PLUS_EQUAL, T_MUL_EQUAL, T_MINUS_EQUAL, T_ISSET, T_OPEN_TAG, T_DOC_COMMENT ])) {
            // Ignore these tokens
        }else if ($name === T_DOUBLE_ARROW){
          if (in_array('inside_view', $this->flags)) {
              $this->output .=':';
          }
          }elseif ($this->checkAndProcessTagsNeedToBeClosed($token)) {
          }else if($this->checkAndProcessSameTagsOnBothThem($token)){
          }else if($this->checkAndProcessVariable($token)){
          }else if($this->checkAndProcessString($token)){
          }else if($this->checkAndProcessEchoAndPrint($token)){
          }else if($this->checkAndProcessCompareOperators($token)){
          }else if($this->checkAndProcessIfBlocks($token)){
          }else if($this->checkAndProcessComments($token)){
          }else if($this->checkAndProcessObjectOperator($token)){
          }else if($this->checkAndProcessEmpty($token)){
          }else if($this->checkAndProcessForBlocks($token)){
          }else {
            // unsupported
            echo "WARNING: UNSUPPORTED ".token_name($token[0])."\n";
          }
      }else if($this->checkIsStringToken($token)) {
        switch ($token) {
          case '[':
            // Accessing key of an array
            $next = next($this->tokens);
            if (is_array($next) && $next[0] == T_CONSTANT_ENCAPSED_STRING) {
                if ($next[1][1] == '#') {
                    // verify unsupported first characters
                    $this->output .= "[$next[1]]";
                } else {
                    if (in_array('inside_view', $this->flags) && !in_array('inside_view_object', $this->flags)) {
                        $this->output .= '{'.substr($next[1], 1, -1);
                        $this->flags[] = 'inside_view_object';
                    } else {
                        // else remove quotes
                        $this->output .= '.'.substr($next[1], 1, -1);
                    }
                }
            } elseif (is_array($next) && $next[0] == T_VARIABLE) {
                // Remove leading '$'
                $this->output .= '.'.substr($next[1], 1);
            } elseif (is_array($next) && $next[0] == T_LNUMBER) {
                // no treatment on numbers
                $this->output .= '.'.$next[1];
            } elseif (is_array($next)) {
                die("Unsupported array/object traversing with ".token_name($next[0]));
            } else {
                die("Unsupported array/object traversing with $next");
            }
            break;
          case '{':
            // This might close an expression
            if (count($this->nesting)) {
                $this->output .= array_pop($this->nesting);
            }
            break;
          case '}':
            function processCloseIfBlock($tokenIndex, $context){
              $nextIsElseIf = findNextZendToken($tokenIndex, $context->tokens, [T_ELSEIF]);
              $nextIsElse = findNextZendToken($tokenIndex, $context->tokens, [T_ELSE]);

              if ($context->checkIsPHPToken($nextIsElseIf)) {
                  $context->flags[] = 'inside_elseif';
              } elseif ($context->checkIsPHPToken($nextIsElse)) {
                  $context->flags[] = 'inside_else';
              } else {
                  $context->output .= '{% endif %}';
              }
            }
            // This might close a if block
            if (in_array('inside_if', $this->flags)) {
                // Verify next token is not empty()
                unset($this->flags[array_search('inside_if', $this->flags)]);
                processCloseIfBlock($currentTokenIndex, $this);
                break;
            } elseif (in_array('inside_elseif', $this->flags)) {
                // Verify next token is not empty()
                unset($this->flags[array_search('inside_elseif', $this->flags)]);
                processCloseIfBlock($currentTokenIndex, $this);
                break;
            } elseif (in_array('inside_else', $this->flags)) {
                unset($this->flags[array_search('inside_else', $this->flags)]);
                $this->output .= '{% endif %}';
                break;
            }
            break;
          case '!':
            // Verify next token is not empty()
            $next = next($this->tokens);
            if (is_array($next) && $next[0] == T_EMPTY) {
                // if empty(), ignore
            } else {
                prev($this->tokens);
                $this->output .= "not ";
            }
            break;
        case '.':
            // Concatenation
            $this->output .= '~';
            break;
        case '?':
            // Ternary condition
            if (in_array('inside_empty_condition', $this->flags)) {
                $this->output .= array_pop($this->nesting);
                unset($this->flags[array_search('inside_empty_condition', $this->flags)]);
            }
            $this->flags[] = 'inside_ternary';
            $this->output .= $token;
            break;
          case ';':
              // This close a function call or a print
              if (in_array('inside_func_call', $this->flags)) {
                  $this->output .= array_pop($this->nesting);
                  unset($this->flags[array_search('inside_func_call', $this->flags)]);
              }
              if (in_array('}}', $this->nesting)) {
                  $this->output .= ' '.array_pop($this->nesting);
              }

              unset($this->flags[array_search('inside_view', $this->flags)]);
              unset($this->flags[array_search('inside_view_object', $this->flags)]);
              break;
          case ':':
              // This must print inside ternary, else we don't care
              if (in_array('inside_ternary', $this->flags)) {
                  $this->output .= $token;
                  unset($this->flags[array_search('inside_ternary', $this->flags)]);
              }
              // This might close a if expression
              if (in_array('inside_condition', $this->flags)) {
                  $this->output .= ' '.array_pop($this->nesting);
                  unset($this->flags[array_search('inside_condition', $this->flags)]);
              }
              break;
          case ',':
          case '%':
          case '*':
          case '/':
          case '+':
          case '-':
              // Same as twig
              $this->output .= $token;
              break;
          case '(':
          case ')':
          case ']':
              // Ignored
              break;
        }
      }
      next($this->tokens);
    }
  }

  function checkAndProcessEmpty($initialToken){
    $name = $initialToken[0];
    $value = $initialToken[1];

    if($name !== T_EMPTY) return false;

    // !empty() is managed in '!', flag to be closed in '?'
    $this->flags[] = 'inside_empty_condition';
    $this->nesting[] = 'is empty ';

    return true;
  }

  function checkAndProcessObjectOperator($initialToken){
    $name = $initialToken[0];
    $value = $initialToken[1];

    if($name !== T_OBJECT_OPERATOR) return false;
    // This is object call or property ->
    $this->flags[] = 'inside_object_method';
    $this->output .= '.';

    return true;
  }

  function checkAndProcessComments($initialToken){
    $name = $initialToken[0];
    $value = $initialToken[1];

    if($name !== T_COMMENT) return false;

    if ($value[1] == '*') {
      // Multiline comment, remove ' * ' on each line
      $value = preg_replace('@/\*(.+)\s+\*/@sU', '{# $1 #}', $value);
      $value = preg_replace('@\s{3}\*\s@s', '', $value);
      $this->output .= $value;
    } else {
        // Single line comment that may be multiline (lol), so take care of this
        $this->output .= "{# ";
        prev($this->tokens);
        $matches = null;
        while (($token = next($this->tokens)) && is_array($token) && in_array(
            $token[0],
            [T_WHITESPACE, T_COMMENT]
        )) {
            if ($token[0] == T_COMMENT) {
                if (isset($matches[2])) {
                    $this->output .= "$matches[2]";
                }
                preg_match('@^//\s?(.*)(\s*)@', $token[1], $matches);
                $this->output .= "$matches[1]";
            } else {
                $matches[2] .= "$token[1]";
            }
        }
        $this->output .= " #}".$matches[2];
        prev($this->tokens);
    }

    return true;
  }

   function checkAndProcessForBlocks($initialToken){
    $name = $initialToken[0];
    $value = $initialToken[1];

    $token = $initialToken;

    // TODO: RENDER METHOD
    if($name == T_ENDFOR || $name == T_ENDFOREACH){
      $this->output .= '{% endfor %}';
      return true;
    }elseif ($name == T_FOREACH) {
        // foreach($iterated as $index => $value) -> for(value in iterated) _or_ for(index, value in iterated)
        $this->output .= '{% for';
        while (!is_array($token) || !in_array($token[0], [T_VARIABLE, T_STRING])) {
            $token = next($this->tokens);
        }
        $iterated = substr($token[1], 1);
        while (!is_array($token) || $token[0] !== T_AS) {
            $token = next($this->tokens);
        }
        while (!is_array($token) || $token[0] !== T_VARIABLE) {
            $token = next($this->tokens);
        }
        $index_name = substr($token[1], 1);

        // Try to find value in next four tokens
        $i = 6;
        while (($token = next($this->tokens)) && $i) {
            if (is_array($token) && $token[0] == T_VARIABLE) {
                $value = substr($token[1], 1);
                $this->output .= " $index_name, $value in $iterated %}";
                break;
            }
            $i--;
        }
        if (!$i) {
            $this->output .= " $index_name in $iterated %}";
            // We've got too far, go back a bit
            prev($this->tokens);
            prev($this->tokens);
            prev($this->tokens);
            prev($this->tokens);
        }
      return true;
    }

    return false;
  }


  function checkAndProcessIfBlocks($initialToken){
    $name = $initialToken[0];
    $value = $initialToken[1];

    if ($name == T_IF) {
        // There should be an expression after this, so we close it in ':' or '{'
        $this->output .= '{% if';
        $this->nesting[] = '%}';
        $this->flags[] = 'inside_condition';
        $this->flags[] = 'inside_if';
        return true;
    } elseif ($name == T_ENDIF) {
        if (in_array('inside_if', $this->flags)) {
            unset($this->flags[array_search('inside_if', $this->flags)]);
        }
        $this->output .= '{% endif %}';
        return true;
    }elseif ($name == T_ELSEIF) {
        // There should be an expression after this, so we close it in ':' or '{'

        $this->output .= '{% elseif ';
        $this->flags[] = 'inside_condition';
        $this->nesting[] = '%}';
        return true;
    } elseif ($name == T_ELSE) {
        $this->output .= '{% else %} ';
        return true;
    }

    return false;
  }

  function checkAndProcessCompareOperators($initialToken){
    $name = $initialToken[0];
    $value = $initialToken[1];

    if ($name == T_IS_EQUAL || $name == T_IS_IDENTICAL) {
        $this->output .= ' is ';
        return true;
    } elseif ($name == T_IS_NOT_EQUAL || $name == T_IS_NOT_IDENTICAL) {
        $this->output .= ' is not ';
        return true;
    } elseif ($name == T_BOOLEAN_AND) {
        $this->output .= ' and ';
        return true;
    } elseif ($name == T_BOOLEAN_OR) {
        $this->output .= ' or ';
        return true;
    }

    return false;
  }

  function checkAndProcessEchoAndPrint($initialToken){
    $name = $initialToken[0];
    $value = $initialToken[1];
    $index = $initialToken[3];

    if(!($name === T_ECHO || $name === T_PRINT)) return false;
    if ($name === T_ECHO) {
      $next = $this->tokens[$index + 1];

      $next = findNextZendToken($index, $this->tokens, [T_VARIABLE], 'token');
      if (is_array($next) && isset($next['next']) && $next['next'][0] === T_VARIABLE) {
          $nextTokenIndex = $next['index'];
          $nextToken = $this->tokens[$nextTokenIndex];
          $renderTemplate = null;

          $typeFlag = [];
          $routerPath = false;
          $generalFunctionName = false;
          $hasWith = false;
          $isRenderView = false;
          if (is_array($nextToken)) {
              $endOfLine = findNextZendToken($index, $this->tokens, [';'], 'seperator');
              $endOfStatement = findNextZendToken($index, $this->tokens, [','], 'seperator');
              if (isset($endOfStatement) && isset($endOfLine) && $endOfStatement['index'] < $endOfLine['index']) {
                  if ($next['next'][1] === '$view') {
                      $next = $this->tokens[$next['index'] + 3];
                      if ($next[1] === "'router'") {
                          $typeFlag[] = 'router';
                          $routerNameToken = $this->tokens[$next['index'] + 10];
                          $routerPath = $routerNameToken[1];
                      } elseif ($next[1] === 'render') {
                          $isRenderView = true;
                          $renderTemplate = $this->tokens[$nextTokenIndex + 5];
                          if (!isset($renderTemplate)) {
                              return;
                          }
                      }

                      if ($next[1] === "'general'") {
                          $typeFlag[] = 'general';
                          $generalFunctionNameToken = $this->tokens[$next['index'] + 10];
                          $generalFunctionName = $generalFunctionNameToken[1];
                          prev($this->tokens);
                      } else {
                          for ($i=0; $i < ($endOfStatement['index'] - $index); $i++) {
                              next($this->tokens);
                          }
                      }
                  }
                  // For seperator ','
                  next($this->tokens);
                  $hasWith = true;
              } else {
                  $endOfLine = findNextZendToken($index, $this->tokens, [';'], 'seperator');
                  $endOfStatement = findNextZendToken($index, $this->tokens, [')'], 'seperator');
                  if (isset($endOfStatement) && isset($endOfLine) && $endOfStatement['index'] < $endOfLine['index']) {
                      if ($next['next'][1] === '$view') {
                          $cachedIndex = $next['index'];
                          $routerNameToken = $this->tokens[ $cachedIndex + 10];
                          print_r($routerNameToken);

                          $next = $this->tokens[$next['index'] + 3];
                          if ($next[1] === "'router'") {
                              $typeFlag[] = 'router';
                              $routerPath = $routerNameToken[1];
                          } elseif ($next[1] === 'render') {
                              $isRenderView = true;
                              $renderTemplate = $this->tokens[$nextTokenIndex + 5];
                              if (!isset($renderTemplate)) {
                                  return;
                              }
                          }

                          if ($next[1] === "'general'") {
                              $typeFlag[] = 'general';
                              $generalFunctionNameToken = $this->tokens[$next['index'] + 10];
                              $generalFunctionName = $generalFunctionNameToken[1];
                              prev($this->tokens);
                          } else {
                              for ($i=0; $i < ($endOfStatement['index'] - $index); $i++) {
                                  next($this->tokens);
                              }
                          }
                      }
                      // For seperator ','
                      next($this->tokens);
                  }
                  $hasWith = false;
              }
          }

          if ($renderTemplate) {
              $renderTemplatePath = $renderTemplate[1];
              $hasWithStringForOutput = $hasWith ? "with " : '';
              $hasWithStringForNesting = $hasWith ? ' } ' : '';
              $this->output .= "{% include $renderTemplatePath ".$hasWithStringForOutput;
              $this->nesting[] =  $hasWithStringForNesting.'%}';
              $this->flags[] = 'inside_view';
          } elseif ($routerPath) {
              $hasWithStringForOutput = $hasWith ? ", " : '';
              $hasWithStringForNesting = $hasWith ? ' } ' : '';
              $this->output .= "{{ path($routerPath ".$hasWithStringForOutput;
              $this->nesting[] =  $hasWithStringForNesting.') }}';
              $this->flags[] = 'inside_view';
          } elseif ($generalFunctionName) {
              // Print something
              $this->output .= '{{';
              $this->nesting[] = '}}';
              $this->flags = [];
          }
      } else {
          // Print something
          $this->output .= '{{';
          $this->nesting[] = '}}';
      }
  } else {
      // Print something
      $this->output .= '{{';
      $nesting[] = '}}';
  }

    return true;
  }

  function checkAndProcessString($initialToken){
    $name = $initialToken[0];
    $value = $initialToken[1];

    if($name !== T_STRING) return false;

    // TODO: RENDER METHOD
    // Function call
    if ($value == 't') {
        $nest = array_pop($this->nesting);
        $this->nesting[] = '|t '.$nest;
    } elseif ($value == 'render') {
        // Ignore
    } elseif (in_array('inside_object_method', $this->flags) && next($this->tokens) !== '(') {
        $this->output .= $value;
    } else {
        $this->output .= $value.'(';
        $this->nesting[] = ')';
        $this->flags[] = 'inside_func_call';
        echo "Unsupported function $value().\n";
    }

    return true;
  }

  function checkAndProcessVariable($initialToken){
    $name = $initialToken[0];
    $value = $initialToken[1];

    if($name !== T_VARIABLE) return false;

    // Variable, remove leading '$'
    $this->output .= substr($value, 1);

    return true;
  }

  function checkAndProcessSameTagsOnBothThem($initialToken){
    $name = $initialToken[0];
    $value = $initialToken[1];

    if(!in_array($name, [
        T_WHITESPACE,
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_CONSTANT_ENCAPSED_STRING,
        T_LNUMBER,
        T_DNUMBER,
        T_IS_GREATER_OR_EQUAL,
        T_IS_SMALLER_OR_EQUAL,
    ])) return false;

    // These are the same in twig
    $this->output .= $value;

    return true;
  }

  function checkAndProcessTagsNeedToBeClosed($initialToken){
    $name = $initialToken[0];
    $value = $initialToken[1];

    if (!in_array($name, [T_CLOSE_TAG, T_INLINE_HTML])) {
      return false;
    }

    if(!($this->nesting > 0)) return true;
    // When closing tags or displaying HTML, end nesting
    $nest = array_pop($this->nesting);
    // Remove last space for filters
    if ($nest[0] == '|') {
        $this->output = rtrim($this->output, ' ');
    }
    $this->output .= $nest;
    $this->output .= str_replace("?>", "", $value);

    return true;
  }

  function checkAndProcessMathematicalOperator($initialToken, $token, $tokenindex){
    if(!in_array($token[0], [T_OR_EQUAL, T_PLUS_EQUAL, T_MUL_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL])){
      return false;
    }

    $name = $initialToken[0];
    $value = $initialToken[1];

    $this->output .= $value.' '.substr($token[1], 0, 1).' '.$value;
    next($this->tokens);

    return true;
  }

  function checkAndProcessVariableIncremention($initialToken, $token,  $tokenindex){
    if($token[0] == T_INC){
      return false;
    }

    $name = $initialToken[0];
    $value = $initialToken[1];

    $this->output .= "{% $value = $value + 1 %}";
    next($this->tokens);
    next($this->tokens);

    return true;
  }

  function checkAndProcessVariableDecrease($initialToken, $token, $tokenindex){
    if($token[0] == T_INC){
      return false;
    }

    $name = $initialToken[0];
    $value = $initialToken[1];

    $this->output .= "{% $value = $value - 1 %}";
    next($this->tokens);
    next($this->tokens);

    return true;
  }
}


$twigConverter = new TwigConverter();
$twigConverter->convertFile('./sample.html.php');