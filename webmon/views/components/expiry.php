<?php
// 만료일 기준 D-Day 및 색상 클래스 계산 헬퍼 함수
function getExpiryColorAndText($date, $monEnabled) {
    if (!$monEnabled || empty($date)) {
        return ['class' => 'text-slate-400', 'badge' => '', 'dday' => ''];
    }
    
    $today = new DateTime(date('Y-m-d'));
    $expiry = new DateTime($date);
    $interval = $today->diff($expiry);
    $days = (int)$interval->format('%r%a'); // 남은 일수
    
    if ($days < 0) {
        return [
            'class' => 'text-red-600 dark:text-red-400 font-extrabold',
            'badge' => '<span class="bg-red-500 text-white px-2 py-0.5 rounded-md text-[9px] font-black inline-block ml-1.5 animate-pulse shadow-sm shadow-red-500/10">만료됨</span>',
            'dday' => '만료됨'
        ];
    } elseif ($days <= 14) {
        return [
            'class' => 'text-red-600 dark:text-red-400 font-extrabold',
            'badge' => '<span class="bg-red-500 text-white px-2 py-0.5 rounded-md text-[9px] font-black inline-block ml-1.5 shadow-sm shadow-red-500/10">D-' . $days . '</span>',
            'dday' => "D-{$days}"
        ];
    } elseif ($days <= 30) {
        return [
            'class' => 'text-amber-600 dark:text-amber-500 font-extrabold',
            'badge' => '<span class="bg-amber-500 text-white px-2 py-0.5 rounded-md text-[9px] font-black inline-block ml-1.5 shadow-sm shadow-amber-500/10">D-' . $days . '</span>',
            'dday' => "D-{$days}"
        ];
    }
    
    return [
        'class' => 'text-slate-700 dark:text-slate-300 font-bold',
        'badge' => '<span class="bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-200 px-2 py-0.5 rounded-md text-[9px] font-bold inline-block ml-1.5">D-' . $days . '</span>',
        'dday' => "D-{$days}"
    ];
}

