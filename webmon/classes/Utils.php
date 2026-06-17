<?php
class Utils {
    public static function buildChosungRegex($keyword) {
        $cho = [
            'ㄱ'=>'[가-깋]', 'ㄲ'=>'[까-낗]', 'ㄴ'=>'[나-닣]', 'ㄷ'=>'[다-딯]', 'ㄸ'=>'[따-띻]',
            'ㄹ'=>'[라-맇]', 'ㅁ'=>'[마-밓]', 'ㅂ'=>'[바-빟]', 'ㅃ'=>'[빠-삫]', 'ㅅ'=>'[사-싷]',
            'ㅆ'=>'[싸-쓯]', 'ㅇ'=>'[아-잏]', 'ㅈ'=>'[자-짛]', 'ㅉ'=>'[짜-찧]', 'ㅊ'=>'[차-칳]',
            'ㅋ'=>'[카-킿]', 'ㅌ'=>'[타-탛]', 'ㅍ'=>'[파-핗]', 'ㅎ'=>'[하-힣]'
        ];
        $result = '';
        $is_chosung = false;
        for ($i = 0; $i < mb_strlen($keyword, 'UTF-8'); $i++) {
            $c = mb_substr($keyword, $i, 1, 'UTF-8');
            if (isset($cho[$c])) {
                $result .= $cho[$c];
                $is_chosung = true;
            } else {
                $result .= preg_quote($c, '/');
            }
        }
        return $is_chosung ? $result : false;
    }

    public static function readEnv() {
        $path = '/var/www/html/.env';
        if (!file_exists($path)) {
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Strip quotes if any
                if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                    $value = $matches[1];
                }
                $env[$key] = $value;
            }
        }
        return $env;
    }

    public static function writeEnv(array $data) {
        $path = '/var/www/html/.env';
        if (!file_exists($path)) {
            file_put_contents($path, "");
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $newLines = [];
        $writtenKeys = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed) || strpos($trimmed, '#') === 0) {
                $newLines[] = $line;
                continue;
            }
            if (strpos($trimmed, '=') !== false) {
                list($key, $val) = explode('=', $trimmed, 2);
                $key = trim($key);
                if (array_key_exists($key, $data)) {
                    $newLines[] = "{$key}=" . self::escapeEnvValue($data[$key]);
                    $writtenKeys[$key] = true;
                } else {
                    $newLines[] = $line;
                }
            } else {
                $newLines[] = $line;
            }
        }

        foreach ($data as $key => $val) {
            if (!isset($writtenKeys[$key])) {
                $newLines[] = "{$key}=" . self::escapeEnvValue($val);
            }
        }

        file_put_contents($path, implode("\n", $newLines) . "\n");
    }

    private static function escapeEnvValue($value) {
        if (preg_match('/[#\s"\'\\\\]/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }
        return $value;
    }

    public static function highlightCommand($cmd) {
        if (empty($cmd)) {
            return '';
        }

        // 1. Extract quoted strings
        $quotes = [];
        $placeholder_idx = 0;
        
        // Match double quotes or single quotes
        $pattern = '/"([^"]*)"|\'([^\']*)\'/';
        $cmd = preg_replace_callback($pattern, function($matches) use (&$quotes, &$placeholder_idx) {
            $full = $matches[0];
            $quotes[$placeholder_idx] = $full;
            $placeholder = "##QUOTE_PLACEHOLDER_{$placeholder_idx}##";
            $placeholder_idx++;
            return $placeholder;
        }, $cmd);

        // 2. Match and wrap prefixes (e.g. [만료 페이지 감지])
        $cmd = preg_replace('/^(\[(\[[^\]]+\]|[^\]]+)\])/u', '##PREFIX_START##$1##PREFIX_END##', $cmd);

        // 3. Match and wrap keywords: timeout, curl, lynx, env
        $cmd = preg_replace('/(?<![\w-])(timeout|curl|lynx|env)(?![\w-])/i', '##KW_START##$1##KW_END##', $cmd);

        // 4. Match and wrap options starting with - or --
        $cmd = preg_replace('/(-{1,2}[a-zA-Z0-9_-]+)/', '##OPT_START##$1##OPT_END##', $cmd);

        // 5. Match and wrap environment variable assignments
        $cmd = preg_replace('/\b(FORCE_SSL_PROMPT=yes)\b/', '##ENV_START##$1##ENV_END##', $cmd);

        // 6. HTML escape
        $escaped = htmlspecialchars($cmd, ENT_QUOTES, 'UTF-8');

        // 7. Replace formatting placeholders
        $escaped = str_replace('##PREFIX_START##', '<span class="text-rose-500 dark:text-rose-400 font-extrabold mr-1">', $escaped);
        $escaped = str_replace('##PREFIX_END##', '</span>', $escaped);
        
        $escaped = str_replace('##KW_START##', '<span class="text-indigo-600 dark:text-indigo-400 font-black">', $escaped);
        $escaped = str_replace('##KW_END##', '</span>', $escaped);
        
        $escaped = str_replace('##OPT_START##', '<span class="text-amber-600 dark:text-amber-400 font-bold">', $escaped);
        $escaped = str_replace('##OPT_END##', '</span>', $escaped);
        
        $escaped = str_replace('##ENV_START##', '<span class="text-slate-500 dark:text-slate-400 font-bold">', $escaped);
        $escaped = str_replace('##ENV_END##', '</span>', $escaped);

        // 8. Restore quoted strings (escaped and styled)
        foreach ($quotes as $idx => $orig) {
            $escaped_orig = htmlspecialchars($orig, ENT_QUOTES, 'UTF-8');
            
            // Check if it's a URL
            $inner = trim($orig, "\"'");
            if (preg_match('/^https?:\/\//i', $inner)) {
                $styled = '<span class="text-blue-500 dark:text-sky-400 underline font-semibold">' . $escaped_orig . '</span>';
            } else {
                $styled = '<span class="text-emerald-600 dark:text-emerald-400 font-bold">' . $escaped_orig . '</span>';
            }
            
            $escaped = str_replace("##QUOTE_PLACEHOLDER_{$idx}##", $styled, $escaped);
        }

        return $escaped;
    }
}
?>