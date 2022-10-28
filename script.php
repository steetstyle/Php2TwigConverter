#!/usr/bin/env php
<?php
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return '' === $needle || false !== strpos($haystack, $needle);
    }
}

$options = [];

$options['src'] = "./sample.html.php";
$options['dest'] = "./sample.html.twig";

$options['open_tag'] = '<?php';
$options['close_tag'] = '?>';

run($options);

/**
 * Opens files and runs conversion.
 * @param array $options
 */
function run($options)
{
    if (file_exists($options['src'])) {
        if (($sfh = fopen($options['src'], "r")) && ($dfh = fopen($options['dest'], "w"))) {
            try {
                $content = fread($sfh, filesize($options['src']));
                $converted = convert($content, $options);
                fwrite($dfh, $converted);
            } catch (Exception $e) {
                echo $e->getMessage();
            }
            fclose($sfh);
            fclose($dfh);
        }
    }
}

/**
 * @param string $content
 * @param array $options
 * @return string
 */
function convert($content, $options)
{
    preg_match_all('/'.preg_quote($options['open_tag']).'/', $content, $matches, PREG_OFFSET_CAPTURE);

    $context = new Context();

    $result = "";

    $currentPosition = 0;

    foreach ($matches[0] as $match) {
        $codeBlockStart = $match[1];

        $codeBlockEnd = strpos($content, $options['close_tag'], $codeBlockStart) + strlen($options['close_tag']);

        if ($match[1] - $currentPosition !== 0) {
            $result .= substr($content, $currentPosition, $match[1] - $currentPosition);
        }

        $codeBlock = convertCode(trim(substr($content, $codeBlockStart + strlen($options['open_tag']), $codeBlockEnd - $codeBlockStart - strlen($options['close_tag']) - strlen($options['open_tag']))), $context, $options);

        $result = substr_replace($result, $codeBlock, $codeBlockStart, $codeBlockEnd);

        $currentPosition = $codeBlockEnd;
    }

    if ($currentPosition !== strlen($content)) {
        $result .= substr($content, $currentPosition, strlen($content) - $currentPosition);
    }

    return $result;
}

/**
 * @param string $code
 * @param Context $context
 * @param array $options
 * @return string
 */
function convertCode($code, $context, $options)
{
    $metaInformation = parseCode($context, $code, $options);

    $converted = $metaInformation == null ? $code : buildCode($metaInformation, $context);

    return $converted;
}

/**
 * @param $meta
 * @return string
 */
function buildCode($meta, $context)
{
    $code = null;
    switch ($meta->key) {
        case 'echo':
            if ($meta->type == 'render') {
                $code = buildRender($meta);
            } elseif ($meta->type == 'format_number') {
                $code = buildFormatNumber($meta);
            } elseif ($meta->type == 'path') {
                $code = buildPath($meta);
            } elseif ($meta->type == 'url') {
                $code = buildUrl($meta);
            } else {
                $code = buildEcho($meta);
            }
            break;
        case 'extends':
            $code = buildExtends($meta);
            break;
        case 'set':
            $code = buildSet($meta);
            break;
        case 'block':
            $code = buildBlock($meta);
            break;
        case 'endblock':
            $code = '{% endblock %}';
            break;
        case 'foreach':
            $code = buildForeach($meta);
            break;
        case 'endforeach':
            $code = '{% endfor %}';
            break;
        case 'if':
            $code = buildIf($meta);
            break;
        case 'else if':
            $code = buildElseIf($meta);
            break;
        case 'else':
            $code =  buildElse($meta);
            break;
        case 'endif':
            $code = '{% endif %}';
            break;
        case '[':
            $code =  '{';
            break;
        case ']':
            $code = '}';
            break;
    }
    return $code;
}

function buildEcho($meta)
{
    return '{{ '.processExpression($meta->expression->args[0]). ' }}';
}

function buildRender($meta)
{
    if (isset($meta->parameters)) {
        return '{% include '.str_replace('.php', '.twig', $meta->expression->args[0]).' with { '. $meta->parameters->args[0] .' } %}';
    }

    return '{% include '.str_replace('.php', '.twig', $meta->expression->args[0]).' %}';
}

function buildFormatNumber($meta)
{
    if (isset($meta->parameters)) {
        return '{{ '. processExpression($meta->expression->args[0]) . '|format_number(' .var_export($meta->parameters->args[0], 1).') }}';
    }

    return '{{ '. processExpression($meta->expression->args[0]) .'|format_number }}';
}

function buildPath($meta)
{
    if (isset($meta->parameters)) {
        return '{{ path('. $meta->expression->args[0] .', {'. $meta->parameters->args[0] .' }) }}';
    }

    return '{{ path('. $meta->expression->args[0] .') }}';
}

function buildUrl($meta)
{
    if (isset($meta->parameters)) {
        return '{{ url('. $meta->expression->args[0] .', { '. $meta->parameters->args[0] .' }) }}';
    }

    return '{{ url('. $meta->expression->args[0] .') }}';
}

function buildForeach($meta)
{
    if (isset($meta->condition->key)) {
        return '{% for '.processExpression($meta->condition->key).', '.processExpression($meta->condition->value).' in '.processExpression($meta->condition->array_expression).' %}';
    } else {
        return '{% for '.processExpression($meta->condition->value).' in '.processExpression($meta->condition->array_expression).' %}';
    }
}

function buildExtends($meta)
{
    return '{% extends '.processExpression($meta->condition->expression->args[0]).' %}';
}

function buildSet($meta)
{
    return '{% set '.processExpression($meta->condition->expression->args[0]).' = '.processExpression($meta->expression->args[0]).' %}';
}

function buildBlock($meta)
{
    return '{% block '.processExpression($meta->condition->expression->args[0]).' %}';
}

function buildIf($meta)
{
    return '{% if '.processExpression($meta->condition->expression->args[0]).' %}';
}

function buildElseIf($meta)
{
    return '{% elseif '.processExpression($meta->condition->expression->args[0]).' %}';
}

function buildElse($meta)
{
    return '{% else %}';
}

function processExpression($argument)
{
    return str_replace('$', '', $argument);
}

/**
 * @param Context $context
 * @param string $code
 * @param array $options
 * @return null|object
 */
function parseCode($context, $code, $options = [])
{
    $meta = null;
    $token = "";
    for ($index = 0, $size = strlen($code) ; $index < $size ; $index++) {
        if (empty($code[$index])) {
            $token = "";
            continue;
        }

        $token .= $code[$index];
        switch ($token) {
            case 'echo':
                $meta = parseEcho($code, $context);
                $token = "";
                break;
            case 'foreach':
                $meta = parseForeach($code, $context);
                $token = "";
                break;
            case '} else {':
                $meta = parseElse($code, $context);
                $token = "";
                break;
            case '} elseif':
                $meta = parseElseIf($code, $context);
                $token = "";
                break;
            case 'if':
                $meta = parseIf($code, $context);
                $token = "";
                break;
            case '{':
                $context->getCurrentBlock()->multiline = true;
                break;
            case '}':
                $contextBlockKey = $context->getCurrentBlock()->key;
                $checkPrevBlockKey = $context->getPreviousBlock();
                $previousBlockKey = isset($checkPrevBlockKey) ? $context->getPreviousBlock()->key : null;
                if ($contextBlockKey == 'else if' || $contextBlockKey == 'else') {
                    $meta = (object) [
                        'key' =>  (($contextBlockKey != 'else if' && $contextBlockKey != 'else') ? '' : 'endif')
                    ];
                } elseif ($context->getCurrentBlock() != null &&  $context->getCurrentBlock()->multiline) {
                    $meta = (object) [
                        'key' =>  ($contextBlockKey == 'foreach') ? 'endforeach' :
                                  ($contextBlockKey == 'if' ? 'endif' : '')
                    ];
                }
                if (! str_contains($code, '} else if') || ! str_contains($code, '} else {')) {
                } else {
                    $context->popBlock();
                    $token = "";
                }
                break;
            case ':':
                break;
            default:
                if (str_contains($code, '$view->extend')) {
                    $meta = parseExtends($code, $context);
                    $token = "";
                } elseif (str_contains($code, 'slots') && str_contains($code, 'start')) {
                    $meta = parseBlockStart($code, $context);
                    $token = "";
                } elseif (str_contains($code, 'slots') && str_contains($code, 'stop')) {
                    $meta = parseBlockStop($code, $context);
                    $token = "";
                } elseif (str_contains($code, 'slots') && str_contains($code, 'set')) {
                    $meta = parseSet($code, $context);
                    $token = "";
                }
                break;
        }
    }

    return $meta;
}

function parseEcho($code, $context)
{
    $meta = (object) [
        'key' => 'echo'
    ];

    if (str_contains($code, '\'[') || str_contains($code, '\')') || str_contains($code, '[\'')) {
        if (str_contains($code, 'view->render')) {
            $meta->type = 'render';

            // Get route url of path function
            $filePathMatches = [];
            preg_match('/(?=\'){1,}.*(?=).(php|twig)\'/m', $code, $filePathMatches, PREG_UNMATCHED_AS_NULL);
            $meta->expression = parseExpression(trim($filePathMatches[0], '() '));

            // Get parameters of path function
            $parameterMatches = [];
            preg_match('/=?\, \:?(.*?)\)/m', $code, $parameterMatches, PREG_UNMATCHED_AS_NULL);
            if (count($parameterMatches) > 1) {
                $meta->parameters = parseParameters($parameterMatches[1]);
            }
        } elseif (str_contains($code, 'view[\'general\']->formatNumber')) {
            $meta->type = 'format_number';

            // Check has parameters of format number function
            $matches = [];
            preg_match('/(?:formatNumber\()(.*)(?=),/m', $code, $matches, PREG_UNMATCHED_AS_NULL);
            if ($matches) {
                $meta->expression = parseExpression($matches[1]);

                $parameterMatches = [];
                preg_match('/(?:\,)(.*)(?=\);)/m', $code, $parameterMatches, PREG_UNMATCHED_AS_NULL);
                $meta->parameters = parseParameters(str_contains($parameterMatches[1], 'true') ? true : false);
            } else {
                // There is no parameter on format number function
                preg_match('/(?:formatNumber\()(.*)(?=)\)/m', $code, $matches, PREG_UNMATCHED_AS_NULL);
                $meta->expression = parseExpression($matches[1]);
            }
        } elseif (str_contains($code, 'view->router->path')) {
            $meta->type = 'path';

            // Get route url of path function
            $filePathMatches = [];
            preg_match('/(?=\'){1,}.*(?=).(php|twig)\'/m', $code, $filePathMatches, PREG_UNMATCHED_AS_NULL);
            $meta->expression = parseExpression(trim($filePathMatches[0], '() '));

            // Get parameters of path function
            $parameterMatches = [];
            preg_match('/=?\, \:?(.*?)\)/m', $code, $parameterMatches, PREG_UNMATCHED_AS_NULL);
            $meta->parameters = parseParameters($parameterMatches[1]);
        } elseif (str_contains($code, 'view->router->url')) {
            $meta->type = 'url';

            // Get route url of path function
            $filePathMatches = [];
            preg_match('/(?=\'){1,}.*(?=).(php|twig)\'/m', $code, $filePathMatches, PREG_UNMATCHED_AS_NULL);
            $meta->expression = parseExpression(trim($filePathMatches[0], '() '));

            // Get parameters of path function
            $parameterMatches = [];
            preg_match('/=?\, \:?(.*?)\)/m', $code, $parameterMatches, PREG_UNMATCHED_AS_NULL);
            $meta->parameters = parseParameters($parameterMatches[1]);
        } else {
            $meta->type = 'echo';

            $matches = [];
            preg_match('/\s*?\(?.*?\)??\s??(?=;)/', $code, $matches, 0, strpos($code, $meta->key) + strlen($meta->key));

            $expression = $matches[0];
            $expression = str_replace('view[\'cdn\']->getUrl', 'get_cdn_url', $expression);
            $meta->expression = parseExpression(trim($expression, ''));
        }
    } else {
        $meta->type = 'echo';

        $matches = [];
        preg_match('/\s*?\(?.*?\)??\s??(?=;)/', $code, $matches, 0, strpos($code, $meta->key) + strlen($meta->key));
        $meta->expression = parseExpression(trim($matches[0], '() '));
    }

    return $meta;
}

function parseArgument($argument)
{
    return $argument;
}

/**
 * @param string $code
 * @param Context $context
 * @return object
 */
function parseForeach($code, $context)
{
    $operation = 'foreach';

    $meta = (object) [
        'key' => $operation
    ];

    $context->pushBlock((object) ['key' => 'foreach']);

    if (preg_match('/foreach\s*\(.*\)\s*\{.*/', $code) === 1) {
        $context->getCurrentBlock()->multiline = true;
    } else {
        $context->getCurrentBlock()->multiline = false;
    }

    $expressionStartIndex = strpos($code, $operation) + strlen($operation);
    $expressionStartIndex = strpos($code, '(', $expressionStartIndex) + 1;
    $expressionEndIndex = strrpos($code, ')', $expressionStartIndex);

    $condition = explode(' as ', trim(substr($code, $expressionStartIndex, $expressionEndIndex - $expressionStartIndex)));
    $meta->condition = (object) [
        'array_expression' => $condition[0]
    ];
    if (strpos($condition[1], '=>')) {
        $keyValue = explode('=>', $condition[1]);
        $meta->condition->key = trim($keyValue[0]);
        $meta->condition->value = trim($keyValue[1]);
    } else {
        $meta->condition->value = trim($condition[1]);
    }

    return $meta;
}

/**
 * @param string $code
 * @param Context $context
 * @return object
 */
function parseIf($code, $context)
{
    $operation = 'if';

    $context->pushBlock((object) ['key' => 'if', 'multiline' => false]);

    $meta = (object) [
        'key' => $operation
    ];
    if (preg_match('/if\s*\(.*\)\s*\{.*/', $code) === 1) {
        $context->getCurrentBlock()->multiline = true;
    } else {
        $context->getCurrentBlock()->multiline = false;
    }

    $matches = [];

    preg_match('/\(.*\)/', $code, $matches);

    $meta->condition = parseCondition($matches[0]);

    return $meta;
}

/**
 * @param string $code
 * @param Context $context
 * @return object
 */
function parseExtends($code, $context)
{
    $operation = 'extends';

    $context->pushBlock((object) ['key' => 'extends', 'multiline' => false]);
    $context->getCurrentBlock()->multiline = true;

    $meta = (object) [
        'key' => $operation
    ];

    $matches = [];
    preg_match('/((?<![\\\\])[\'"])((?:.(?!(?<![\\\\])\1))*.?)\1/', $code, $matches);
    $meta->condition = parseCondition($matches[0]);

    return $meta;
}

/**
 * @param string $code
 * @param Context $context
 * @return object
 */
function parseBlockStart($code, $context)
{
    $operation = 'block';

    $context->pushBlock((object) ['key' => 'extends', 'block' => false]);
    $context->getCurrentBlock()->multiline = true;

    $meta = (object) [
        'key' => $operation
    ];

    $str = explode('->', $code)[1];
    $matches = [];
    preg_match('/((?<![\\\\])[\'"])((?:.(?!(?<![\\\\])\1))*.?)\1/', $str, $matches);
    $meta->condition = parseCondition($matches[0]);

    return $meta;
}

/**
 * @param string $code
 * @param Context $context
 * @return object
 */
function parseBlockStop($code, $context)
{
    $operation = 'endblock';

    $context->pushBlock((object) ['key' => 'endblock', 'multiline' => false]);
    $context->getCurrentBlock()->multiline = true;

    $meta = (object) [
        'key' => $operation
    ];

    return $meta;
}

/**
 * @param string $code
 * @param Context $context
 * @return object
 */
function parseSet($code, $context)
{
    $operation = 'set';

    $context->pushBlock((object) ['key' => 'set', 'endblock' => false]);
    $context->getCurrentBlock()->multiline = true;

    $meta = (object) [
        'key' => $operation
    ];

    $str = explode('->', $code)[1];
    $matches = [];
    preg_match('/((?<![\\\\])[\'"])((?:.(?!(?<![\\\\])\1))*.?)\1/', $str, $matches);
    $meta->condition = parseCondition(str_replace('-', '_', $matches[2]));
    // Remove string for next match
    $str = str_replace($matches[0], '', $str);

    $matches = [];
    preg_match('/((?<![\\\\])[\'"])((?:.(?!(?<![\\\\])\1))*.?)\1/', $str, $matches);
    $meta->expression = parseExpression($matches[0]);

    return $meta;
}


/**
 * @param string $code
 * @param Context $context
 * @return object
 */
function parseElseIf($code, $context)
{
    $operation = 'else if';

    $context->pushBlock((object) ['key' => 'else if', 'multiline' => false]);

    $meta = (object) [
        'key' => $operation
    ];
    if (preg_match('/else if\s*\(.*\)\s*\{.*/', $code) === 1) {
        $context->getCurrentBlock()->multiline = true;
    } else {
        $context->getCurrentBlock()->multiline = false;
    }

    $matches = [];

    preg_match('/\(.*\)/', $code, $matches);

    $meta->condition = parseCondition($matches[0]);

    return $meta;
}

/**
 * @param string $code
 * @param Context $context
 * @return object
 */
function parseElse($code, $context)
{
    $operation = 'else';

    $context->pushBlock((object) ['key' => 'else', 'multiline' => false]);

    $meta = (object) [
        'key' => $operation
    ];
    if (preg_match('/} else\s*\(.*\)\s*\{.*/', $code) === 1) {
        $context->getCurrentBlock()->multiline = true;
    } else {
        $context->getCurrentBlock()->multiline = false;
    }

    return $meta;
}
/**
 * @param string $condition
 * @return object
 */
function parseCondition($condition)
{
    return (object) [
        'expression' => parseExpression(trim($condition, '\t\n\r\0\x0B'))
    ];
}

/**
 * @param string $expression
 * @return object
 */
function parseExpression($expression)
{
    return (object) [
        'args' => [str_replace(array('!', '&&'), array(' not ', 'and'), $expression)]
    ];
}

/**
 * @param string $expression
 * @return object
 */
function parseParameters($parameters)
{
    return (object) [
        'args' => [$parameters]
    ];
}


class Context
{
    private $_blocks = [];

    public function _construct()
    {
    }

    public function getCurrentBlock()
    {
        return count($this->_blocks) !== 0 ? $this->_blocks[count($this->_blocks) - 1] : null;
    }

    public function getPreviousBlock()
    {
        return count($this->_blocks) > 2 ? $this->_blocks[count($this->_blocks) - 2] : null;
    }

    public function pushBlock($block)
    {
        return array_push($this->_blocks, $block);
    }

    public function popBlock()
    {
        return array_pop($this->_blocks);
    }
}
