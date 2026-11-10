const charts = {};

function initChartDatePickers(no) {
    const startInput = document.getElementById('chart-start-' + no);
    const endInput = document.getElementById('chart-end-' + no);
    if (!startInput || !endInput || typeof flatpickr === 'undefined') return;
    if (startInput._flatpickr) return;

    const fpLocale = document.documentElement.lang === 'ko' ? 'ko' : 'en';
    flatpickr(startInput, {
        enableTime: true,
        enableSeconds: true,
        dateFormat: "Y-m-d H:i:S",
        time_24hr: true,
        defaultDate: new Date(Date.now() - 24 * 60 * 60 * 1000),
        locale: fpLocale,
        allowInput: true
    });
    flatpickr(endInput, {
        enableTime: true,
        enableSeconds: true,
        dateFormat: "Y-m-d H:i:S",
        time_24hr: true,
        defaultDate: new Date(),
        locale: fpLocale,
        allowInput: true
    });
}

// 지연 Flatpickr 초기화: 입력 포커스 시에도 자동 연동
document.addEventListener('focusin', event => {
    if (event.target && event.target.id && (event.target.id.startsWith('chart-start-') || event.target.id.startsWith('chart-end-'))) {
        const match = event.target.id.match(/\d+$/);
        if (match) initChartDatePickers(match[0]);
    }
});

function toggleAccordion(event, no) {
    if (event) event.stopPropagation();
    const accordion = document.getElementById('accordion-' + no);
    const arrow = document.getElementById('arrow-' + no);
    const toggle = document.getElementById('chart-toggle-' + no);
    if (!accordion) return;

    const isKo = document.documentElement.lang === 'ko';
    if (accordion.classList.contains('hidden')) {
        accordion.classList.remove('hidden');
        accordion.classList.add('flex');
        if (arrow) arrow.style.transform = 'rotate(180deg)';
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-label', isKo ? '응답 시간 그래프 닫기' : 'Hide response-time chart');
        }
        initChartDatePickers(no);
        loadChart(no, '1d');
    } else {
        accordion.classList.remove('flex');
        accordion.classList.add('hidden');
        if (arrow) arrow.style.transform = 'rotate(0deg)';
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', isKo ? '응답 시간 그래프 열기' : 'Show response-time chart');
        }
    }
}

async function loadChart(no, range, start = '', end = '') {
    const canvas = document.getElementById('chart-canvas-' + no);
    const avgSpan = document.getElementById('avg-time-' + no);
    const rateSpan = document.getElementById('success-rate-' + no);
    if (!canvas) return;

    try {
        let url = `get_chart_data.php?no=${no}`;
        if (start && end) {
            url += `&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
        } else {
            url += `&range=${range}`;
        }
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.error) {
            console.error(data.error);
            return;
        }

        // 데이터 파싱
        const labels = data.map(item => {
            const dt = new Date(item.time);
            if (range === '1d') {
                return dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } else if (!range) {
                return (dt.getMonth() + 1) + '/' + dt.getDate() + ' ' + dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
            } else {
                return (dt.getMonth() + 1) + '/' + dt.getDate() + ' ' + dt.getHours() + 'h';
            }
        });
        const responseTimes = data.map(item => item.response_time);
        const results = data.map(item => item.result);
        const formatChartTimestamp = timestamp => {
            const parsed = new Date(timestamp.replace(' ', 'T'));
            return Number.isNaN(parsed.getTime()) ? timestamp : parsed.toLocaleString();
        };
        const isKo = document.documentElement.lang === 'ko';

        // 통계 연산
        if (responseTimes.length > 0) {
            const sum = responseTimes.reduce((a, b) => a + b, 0);
            const avg = sum / responseTimes.length;
            if (avgSpan) avgSpan.innerText = avg.toFixed(3);

            const successes = results.filter(r => r > 0).length;
            const rate = (successes / results.length) * 100;
            if (rateSpan) rateSpan.innerText = rate.toFixed(1);
        } else {
            if (avgSpan) avgSpan.innerText = '-';
            if (rateSpan) rateSpan.innerText = '-';
        }

        // 이전 차트 해제
        if (charts[no]) {
            charts[no].destroy();
        }

        // 차트 그리기
        const ctx = canvas.getContext('2d');
        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? '#cbd5e1' : '#64748b';
        const gridColor = isDark ? '#334155' : '#e2e8f0';

        const showTime = document.getElementById('show-time-' + no) ? document.getElementById('show-time-' + no).checked : true;
        const showRate = document.getElementById('show-rate-' + no) ? document.getElementById('show-rate-' + no).checked : true;

        // 장애(실패) 시점 하이라이팅 스타일 계산
        const labelsLength = labels.length;
        let ptColors, ptBorderColors, ptRadii, ptHoverRadii;

        if (showRate) {
            ptColors = results.map(r => r > 0 ? '#18aee7' : '#ef4444');
            ptBorderColors = results.map(r => r > 0 ? '#18aee7' : '#ef4444');
            ptRadii = results.map(r => r > 0 ? (labelsLength > 50 ? 0 : 1.5) : 5);
            ptHoverRadii = results.map(r => r > 0 ? 4 : 7);
        } else {
            ptColors = results.map(() => '#18aee7');
            ptBorderColors = results.map(() => '#18aee7');
            ptRadii = results.map(() => labelsLength > 50 ? 0 : 1.5);
            ptHoverRadii = results.map(() => 4);
        }

        charts[no] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                resultsRaw: results,
                responseTimesRaw: responseTimes,
                datasets: [
                    {
                        label: 'Response Time (s)',
                        data: responseTimes,
                        borderColor: '#18aee7',
                        backgroundColor: 'rgba(24, 174, 231, 0.08)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: ptColors,
                        pointBorderColor: ptBorderColors,
                        pointRadius: ptRadii,
                        pointHoverRadius: ptHoverRadii,
                        yAxisID: 'y',
                        hidden: !showTime
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function(items) {
                                if (!items.length) return '';
                                const point = data[items[0].dataIndex];
                                const startTime = formatChartTimestamp(point.period_start || point.time);
                                const endTime = formatChartTimestamp(point.period_end || point.time);
                                const sampleCount = point.sample_count || 1;
                                if (sampleCount === 1) return startTime;
                                return isKo
                                    ? `${startTime} ~ ${endTime} (${sampleCount}회 평균)`
                                    : `${startTime} – ${endTime} (avg. of ${sampleCount} checks)`;
                            },
                            label: function(context) {
                                const point = data[context.dataIndex];
                                const responseLabel = isKo ? '평균 응답시간' : 'Avg response time';
                                const resultLabel = isKo ? '결과' : 'Result';
                                const status = point.result > 0
                                    ? (isKo ? '정상' : 'Success')
                                    : (isKo ? '장애 포함' : 'Failure detected');
                                const lines = [
                                    `${responseLabel}: ${Number(point.response_time).toFixed(3)}s`,
                                    `${resultLabel}: ${status}`
                                ];
                                if ((point.sample_count || 1) > 1) {
                                    lines.push(isKo
                                        ? `장애 ${point.failure_count}회 / 전체 ${point.sample_count}회`
                                        : `${point.failure_count} failures / ${point.sample_count} checks`);
                                }
                                return lines;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxTicksLimit: 8,
                            color: textColor,
                            font: { size: 9 }
                        }
                    },
                    y: {
                        type: 'linear',
                        display: showTime,
                        position: 'left',
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            font: { size: 9 }
                        },
                        title: {
                            display: true,
                            text: 'Response Time (s)',
                            color: textColor,
                            font: { size: 9, weight: 'bold' }
                        }
                    }
                }
            }
        });

    } catch (err) {
        console.error('Failed to load chart data:', err);
    }
}

function updateChartVisibility(no) {
    const chart = charts[no];
    if (!chart) return;
    
    const showTime = document.getElementById('show-time-' + no) ? document.getElementById('show-time-' + no).checked : true;
    const showRate = document.getElementById('show-rate-' + no) ? document.getElementById('show-rate-' + no).checked : true;
    
    chart.setDatasetVisibility(0, showTime);
    chart.options.scales.y.display = showTime;
    
    const dataset = chart.data.datasets[0];
    const results = chart.data.resultsRaw;
    const labelsLength = chart.data.labels.length;
    
    if (showRate) {
        dataset.pointBackgroundColor = results.map(r => r > 0 ? '#18aee7' : '#ef4444');
        dataset.pointBorderColor = results.map(r => r > 0 ? '#18aee7' : '#ef4444');
        dataset.pointRadius = results.map(r => r > 0 ? (labelsLength > 50 ? 0 : 1.5) : 5);
        dataset.pointHoverRadius = results.map(r => r > 0 ? 4 : 7);
    } else {
        dataset.pointBackgroundColor = results.map(() => '#18aee7');
        dataset.pointBorderColor = results.map(() => '#18aee7');
        dataset.pointRadius = results.map(() => labelsLength > 50 ? 0 : 1.5);
        dataset.pointHoverRadius = results.map(() => 4);
    }
    
    chart.update();
}

async function loadCustomChart(no) {
    const isKo = document.documentElement.lang === 'ko';
    const startVal = document.getElementById('chart-start-' + no).value;
    const endVal = document.getElementById('chart-end-' + no).value;
    if (!startVal || !endVal) {
        showToast(isKo ? '시작일과 종료일을 모두 선택해주세요.' : 'Please select both start and end dates.', 'warning');
        return;
    }
    
    const startDate = new Date(startVal.replace(/-/g, '/'));
    const endDate = new Date(endVal.replace(/-/g, '/'));
    
    if (startDate > endDate) {
        showToast(isKo ? '시작일은 종료일보다 이전 날짜여야 합니다.' : 'The start date must be before the end date.', 'warning');
        return;
    }
    
    const now = new Date();
    if (startDate > now || endDate > now) {
        showToast(isKo ? '현재 시간 이후는 조회할 수 없습니다.' : 'You cannot query future times.', 'warning');
        return;
    }
    
    loadChart(no, '', startVal, endVal);
}
