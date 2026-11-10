                        <?php 
                        if($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()): 
                                $is_disabled = ($row['check'] == 'disable');
                                
                                // 스케줄 상태 판별 (일시 무음 시간 판별)
                                $is_scheduled = (($row['sched_enabled'] ?? 0) == 1);
                                $is_currently_off_schedule = false;
                                if ($is_scheduled && !$is_disabled && !empty($row['mute_until'])) {
                                    $now = new DateTime();
                                    $mute_until = new DateTime($row['mute_until']);
                                    if ($now < $mute_until) {
                                        $is_currently_off_schedule = true;
                                    }
                                }

                                if ($is_disabled) {
                                    $border_color = 'bg-gray-400';
                                    $status_class = 'text-gray-400';
                                    $check_text = $t['status_disable'];
                                } elseif ($is_currently_off_schedule) {
                                    $border_color = 'bg-violet-400';
                                    $status_class = 'text-violet-500';
                                    $check_text = $current_lang == 'KO' ? '스케줄 무음' : 'Sched Mute';
                                } else {
                                    $border_color = $row['value'] > 0 ? 'bg-[#18aee7]' : 'bg-red-500';
                                    $status_class = ($row['check'] == 'alarm') ? "text-green-500" : "text-orange-500";
                                    $check_text = ($row['check'] == 'alarm') ? $t['status_alarm'] : $t['status_noalarm'];
                                }

                                $result_text = $row['value'] > 0 ? $t['v_success'] : $t['v_fail'];
                                $result_color = $row['value'] > 0 ? 'text-green-500' : 'text-red-500';
                                if ($is_currently_off_schedule) {
                                    $result_text = $current_lang == 'KO' ? '대기중' : 'Standby';
                                    $result_color = 'text-violet-400';
                                }
                                $group_name_display = htmlspecialchars($row['group_name'] ?: '기본 그룹');
                        ?>
                        
                        <div data-monitor-id="<?= (int)$row['no'] ?>" class="monitor-item group relative bg-[var(--card-bg)] overflow-visible transition-all border border-[var(--border-color)] rounded-xl <?= $is_disabled ? 'opacity-60' : '' ?> hover:shadow-md">
                            <div class="monitor-status-edge absolute top-0 left-0 w-1.5 h-full <?= $border_color ?>"></div>

                            <?php if (($current_view ?? 'card') === 'card'): ?>
                                <?php include __DIR__ . '/item_card.php'; ?>
                            <?php elseif ($current_view === 'list'): ?>
                                <?php include __DIR__ . '/item_list.php'; ?>
                            <?php endif; ?>
                        </div>
                        <?php 
                            endwhile; 
                        } else {
                            echo "<div class='col-span-full text-center py-10 text-[var(--text-sub)]'>데이터가 없습니다.</div>";
                        }
                        ?>
