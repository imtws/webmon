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
}
?>