<?php
/** Parse literal connection settings without executing WordPress/config code. */
function orgavela_config_literal(string $literal): string {
    $quote = substr($literal, 0, 1);
    $body = substr($literal, 1, -1);
    if ($quote === "'") {
        return strtr($body, ["\\\\" => "\\", "\\'" => "'"]);
    }
    if ($quote === '"') {
        // No interpolation tokens are accepted by the caller.
        return stripcslashes($body);
    }
    throw new RuntimeException('Unsupported configuration literal');
}

function orgavela_parse_config(string $code): array {
    $tokens = array_values(array_filter(token_get_all($code), static function ($token) {
        return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }));
    $wanted = ['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST'];
    $values = [];
    $prefix = null;
    foreach ($tokens as $i => $token) {
        if (is_array($token) && $token[0] === T_STRING && strtolower($token[1]) === 'define') {
            if (($tokens[$i + 1] ?? null) !== '(' || !is_array($tokens[$i + 2] ?? null) ||
                $tokens[$i + 2][0] !== T_CONSTANT_ENCAPSED_STRING ||
                ($tokens[$i + 3] ?? null) !== ',') continue;
            $name = orgavela_config_literal($tokens[$i + 2][1]);
            if (!in_array($name, $wanted, true)) continue;
            if (!is_array($tokens[$i + 4] ?? null) || $tokens[$i + 4][0] !== T_CONSTANT_ENCAPSED_STRING ||
                ($tokens[$i + 5] ?? null) !== ')' || isset($values[$name])) {
                throw new RuntimeException('Ambiguous or nonliteral DB configuration');
            }
            $values[$name] = orgavela_config_literal($tokens[$i + 4][1]);
        }
        if (is_array($token) && $token[0] === T_VARIABLE && $token[1] === '$table_prefix') {
            if (($tokens[$i + 1] ?? null) !== '=' || !is_array($tokens[$i + 2] ?? null) ||
                $tokens[$i + 2][0] !== T_CONSTANT_ENCAPSED_STRING ||
                ($tokens[$i + 3] ?? null) !== ';' || $prefix !== null) {
                throw new RuntimeException('Ambiguous or nonliteral table prefix');
            }
            $prefix = orgavela_config_literal($tokens[$i + 2][1]);
        }
    }
    foreach ($wanted as $name) if (!isset($values[$name]) || $values[$name] === '') {
        throw new RuntimeException('Missing literal DB configuration');
    }
    if (!is_string($prefix) || !preg_match('/^[A-Za-z0-9_]{1,64}$/D', $prefix)) {
        throw new RuntimeException('Unsafe table prefix');
    }
    $values['table_prefix'] = $prefix;
    return $values;
}

