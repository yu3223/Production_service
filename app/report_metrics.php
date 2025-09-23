<?php
echo "[report_metrics.php] Metrics script started...\n";

// 取得 host 名稱
$host = getenv('SERVICE_ID') ?: gethostname();
$key = "metrics:$host";

// 初始化 Redis
try {
    $redis = new Redis();
    $redis->connect('10.1.1.94', 6379);
} catch (Exception $e) {
    echo "[ERROR] Redis connect failed: " . $e->getMessage() . "\n";
    exit(1);
}


function getCPU(): int {
    return (int) shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print int($2 + $4)}'");
}

function getCPUCoreCount(): int {
    return (int) shell_exec("nproc");
}

function getMemory(): int {
    $memInfo = explode("\n", trim(shell_exec("free")));
    $memLine = preg_split('/\s+/', $memInfo[1]);
    return (int)(($memLine[2] / $memLine[1]) * 100);
}

function getTotalMemory(): int {
    $memInfo = explode("\n", trim(shell_exec("free")));
    $memLine = preg_split('/\s+/', $memInfo[1]);
    return (int)$memLine[1]; // kB
}

function getLatency(): int {
    $latency = shell_exec("curl -o /dev/null -s -w '%{time_total}' http://10.1.1.90:8081/api/v1/products");
    return (int)(floatval($latency) * 1000); // 秒轉毫秒
}

function getLoadAverage(): float {
    $load = sys_getloadavg();
    return $load[0]; // 1 分鐘負載
}

function getDiskIO(): array {
    $diskStats = shell_exec("iostat -dx | grep -E 'sda|vda'");
    $diskStatsArr = explode(" ", preg_replace('/\s+/', ' ', trim($diskStats)));
    return [
        'read_kb' => floatval($diskStatsArr[5] ?? 0),
        'write_kb' => floatval($diskStatsArr[6] ?? 0)
    ];
}

function getNetworkIO(): array {
    $iface = "eth0";

    // 取得第一次讀數
    $stats1 = file_get_contents("/proc/net/dev");
    preg_match("/{$iface}:\s*(\d+).*?(\d+)\s*$/m", $stats1, $matches1);
    $in1 = isset($matches1[1]) ? (int)$matches1[1] : 0;
    $out1 = isset($matches1[2]) ? (int)$matches1[2] : 0;

    // 等待 1 秒後再取一次
    sleep(1);

    $stats2 = file_get_contents("/proc/net/dev");
    preg_match("/{$iface}:\s*(\d+).*?(\d+)\s*$/m", $stats2, $matches2);
    $in2 = isset($matches2[1]) ? (int)$matches2[1] : 0;
    $out2 = isset($matches2[2]) ? (int)$matches2[2] : 0;

    // 計算 1 秒內的傳輸量差值（轉換為 KB）
    $bytesIn = max(0, $in2 - $in1) / 1024.0;
    $bytesOut = max(0, $out2 - $out1) / 1024.0;

    return [
        'bytes_in_kbps' => round($bytesIn, 2),
        'bytes_out_kbps' => round($bytesOut, 2)
    ];
}


while (true) {
    $cpu      = getCPU();
    $mem      = getMemory();
    $latency  = getLatency();
    $load     = getLoadAverage();
    $diskIO   = getDiskIO();
    $netIO    = getNetworkIO();
    $cpuCores = getCPUCoreCount();
    $totalMem = getTotalMemory();

    $metrics = [
        'host'       => $host,
        'cpu'        => $cpu,
        'mem'        => $mem,
        'latency'    => $latency,
        'load'       => $load,
        'disk_io'    => json_encode($diskIO),
        'network_io' => json_encode($netIO),
        'cpu_cores'  => $cpuCores,
        'mem_total'  => $totalMem
    ];

    $redis->hMSet($key, $metrics);
    $redis->expire($key, 30);

    echo "[{$host}] Sent | CPU={$cpu}%, MEM={$mem}%, Latency={$latency}ms, Load={$load}, DiskIO=" . json_encode($diskIO) . ", NetIO=" . json_encode($netIO) . "\n";

    sleep(5);
}
