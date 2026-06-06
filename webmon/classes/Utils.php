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
}
?>